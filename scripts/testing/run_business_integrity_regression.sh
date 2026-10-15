#!/usr/bin/env bash
set -u

# OH-AUTO1-033 governed regression orchestrator.
# Existing behavioral probes own their assertions; this script owns only
# isolated process provisioning, authorizer invocation, teardown, and reporting.

set -o pipefail

REPO=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
PHP_BIN=${PHP_BIN:-php}
MYSQL_BIN=${MYSQL_BIN:-/opt/homebrew/bin/mysql}
MYSQLD_BIN=${MYSQLD_BIN:-/opt/homebrew/bin/mysqld}
MYSQLADMIN_BIN=${MYSQLADMIN_BIN:-/opt/homebrew/bin/mysqladmin}
MYSQL_BASEDIR=${MYSQL_BASEDIR:-/opt/homebrew/opt/mysql}
TMP_BASE=${OH_TEST_TMP_BASE:-/private/var/folders/_w/4wgfpvdd1_55pjmgt2yt99740000gn/T}

if [ ! -d "$TMP_BASE" ]; then
    echo "FAIL: disposable test temp root is unavailable: $TMP_BASE" >&2
    exit 2
fi

INSTALLED_PID=$(lsof -nP -t -iTCP:3306 -sTCP:LISTEN 2>/dev/null | head -n1 || true)
TOTAL=0
FAILED=0

run_static() {
    label=$1
    script=$2
    TOTAL=$((TOTAL + 1))
    if "$PHP_BIN" "$REPO/$script"; then
        echo "SUITE PASS $label"
    else
        echo "SUITE FAIL $label" >&2
        FAILED=$((FAILED + 1))
    fi
}

run_disposable_probe() {
    label=$1
    prefix=$2
    probe=$3
    TOTAL=$((TOTAL + 1))

    # MySQL limits Unix socket paths to 103 bytes; keep the random temp root
    # intentionally short while retaining the logical suite label in reports.
    root=$(mktemp -d "$TMP_BASE/o33.XXXXXX") || {
        echo "SUITE FAIL $label: cannot create temp root" >&2
        FAILED=$((FAILED + 1))
        return
    }
    data="$root/datadir"
    socket="$root/mysql.sock"
    pidfile="$root/mysql.pid"
    errorlog="$root/mysql.err"
    source_workspace="$root/source-workspace"
    app_root=''
    mkdir -m 700 "$data" || {
        echo "SUITE FAIL $label: cannot create isolated directories" >&2
        rm -rf "$root"
        FAILED=$((FAILED + 1))
        return
    }
    chmod 700 "$root"

    prepared_json=$("$PHP_BIN" "$REPO/scripts/testing/prepare_isolated_source_root.php" "$source_workspace") || {
        echo "SUITE FAIL $label: isolated source-root preparation failed" >&2
        rm -rf "$root"
        FAILED=$((FAILED + 1))
        return
    }
    app_root=$(printf '%s' "$prepared_json" | "$PHP_BIN" -r '$v=json_decode(stream_get_contents(STDIN),true); if (!is_array($v) || !is_string($v["source_root"] ?? null)) exit(1); echo $v["source_root"];') || {
        echo "SUITE FAIL $label: isolated source-root path was not reported" >&2
        rm -rf "$root"
        FAILED=$((FAILED + 1))
        return
    }
    mkdir -p "$app_root/storage"
    port=$(python3 -c 'import socket;s=socket.socket();s.bind(("127.0.0.1",0));print(s.getsockname()[1]);s.close()')
    safe_label=$(printf '%s' "$label" | tr -c 'A-Za-z0-9_' '_')
    db="oh_test_033_${safe_label}_$(openssl rand -hex 6)"
    user="o33_$(openssl rand -hex 10)"
    pass="ohpass$(openssl rand -hex 16)"

    cat > "$app_root/storage/db_config.php" <<PHP
<?php return ['host'=>'127.0.0.1','port'=>$port,'name'=>'$db','user'=>'$user','pass'=>'$pass','charset'=>'utf8mb4','timezone'=>'UTC'];
PHP

    pid=''
    shutdown_done=0
    cleanup() {
        set +e
        if [ "$shutdown_done" -eq 0 ] && [ -f "$pidfile" ]; then
            "$MYSQLADMIN_BIN" --no-defaults --protocol=SOCKET --socket="$socket" -uroot shutdown >/dev/null 2>&1 || true
            pid_from_file=$(cat "$pidfile" 2>/dev/null || true)
            [ -z "$pid_from_file" ] || kill "$pid_from_file" 2>/dev/null || true
        fi
        if [ -d "$source_workspace" ]; then
            "$PHP_BIN" "$REPO/scripts/testing/cleanup_isolated_source_root.php" "$source_workspace" >/dev/null 2>&1 || true
        fi
        rm -rf "$root"
    }

    "$MYSQLD_BIN" --no-defaults --initialize-insecure --basedir="$MYSQL_BASEDIR" --datadir="$data" --log-error="$errorlog" >/dev/null 2>&1 || {
        echo "SUITE FAIL $label: MySQL initialization failed" >&2
        cleanup
        FAILED=$((FAILED + 1))
        return
    }
    "$MYSQLD_BIN" --no-defaults --basedir="$MYSQL_BASEDIR" --datadir="$data" --socket="$socket" --pid-file="$pidfile" --log-error="$errorlog" --bind-address=127.0.0.1 --port="$port" --skip-name-resolve --skip-log-bin --secure-file-priv=NULL >"$root/server.stdout" 2>&1 &
    pid=$!
    ready=0
    for _ in $(seq 1 120); do
        if "$MYSQLADMIN_BIN" --no-defaults --protocol=SOCKET --socket="$socket" -uroot ping >/dev/null 2>&1; then
            ready=1
            break
        fi
        kill -0 "$pid" 2>/dev/null || break
        sleep .25
    done
    if [ "$ready" -ne 1 ]; then
        echo "SUITE FAIL $label: disposable MySQL did not become ready" >&2
        echo "--- $label MySQL error log ---" >&2
        cat "$errorlog" >&2 2>/dev/null || true
        echo "--- $label MySQL stdout ---" >&2
        cat "$root/server.stdout" >&2 2>/dev/null || true
        cleanup
        FAILED=$((FAILED + 1))
        return
    fi

    if ! "$PHP_BIN" -r 'require $argv[1]; try { DisposableDbTestConfigurationGuard::authorize(["repo"=>$argv[2],"app_root"=>$argv[3],"resource_root"=>$argv[4],"host"=>"127.0.0.1","port"=>$argv[5],"name"=>$argv[6],"user"=>$argv[7]]); echo "configuration-guard=PASS\n"; } catch (Throwable $e) { fwrite(STDERR, "configuration-guard=FAIL\n"); exit(1); }' \
        "$REPO/scripts/testing/DisposableDbTestConfigurationGuard.php" "$REPO" "$app_root" "$root" "$port" "$db" "$user"
    then
        echo "SUITE FAIL $label: disposable configuration identity rejected before SQL" >&2
        cleanup
        FAILED=$((FAILED + 1))
        return
    fi

    if ! "$PHP_BIN" -r 'require $argv[1];$r=DisposableDbExecutionAuthorizer::authorize((int)$argv[2],$argv[3],$argv[4],$argv[5],(int)$argv[6]);if(!$r["authorized"]){fwrite(STDERR,json_encode($r,JSON_UNESCAPED_SLASHES));exit(1);}echo "authorizer=PASS checks=",count($r["checks"]),"\n";' \
        "$REPO/scripts/testing/DisposableDbExecutionAuthorizer.php" "$pid" "$socket" "$data" "$root" "$port"
    then
        echo "SUITE FAIL $label: isolation authorizer rejected instance before SQL" >&2
        cleanup
        FAILED=$((FAILED + 1))
        return
    fi

    config_hash_before=$($PHP_BIN -r 'echo hash_file("sha256", $argv[1]);' "$app_root/storage/db_config.php")

    if ! "$MYSQL_BIN" --no-defaults --protocol=SOCKET --socket="$socket" -uroot <<SQL >/dev/null
CREATE DATABASE \`$db\`;
CREATE USER '$user'@'127.0.0.1' IDENTIFIED BY '$pass';
GRANT ALL PRIVILEGES ON \`$db\`.* TO '$user'@'127.0.0.1';
SQL
    then
        echo "SUITE FAIL $label: disposable database creation failed" >&2
        cleanup
        FAILED=$((FAILED + 1))
        return
    fi

    started=$(date +%s)
    set +e
    if [ "$prefix" = "OH_PROC" ]; then
        OH_PROC_REPO="$REPO" OH_PROC_APP_ROOT="$app_root" OH_PROC_DB_PID="$pid" OH_PROC_DB_SOCKET="$socket" OH_PROC_DB_DATADIR="$data" OH_PROC_DB_RESOURCE_ROOT="$root" OH_PROC_DB_PORT="$port" OH_PROC_DB_NAME="$db" OH_PROC_DB_USER="$user" "$PHP_BIN" "$app_root/$probe"
        rc=$?
    elif [ "$prefix" = "OH_SBAIO" ]; then
        OH_SBAIO_REPO="$REPO" OH_SBAIO_APP_ROOT="$app_root" OH_SBAIO_DB_PID="$pid" OH_SBAIO_DB_SOCKET="$socket" OH_SBAIO_DB_DATADIR="$data" OH_SBAIO_DB_RESOURCE_ROOT="$root" OH_SBAIO_DB_PORT="$port" OH_SBAIO_DB_NAME="$db" OH_SBAIO_DB_USER="$user" "$PHP_BIN" "$app_root/$probe"
        rc=$?
    elif [ "$prefix" = "OH_MFG_BOM" ]; then
        OH_MFG_BOM_REPO="$REPO" OH_MFG_BOM_APP_ROOT="$app_root" OH_MFG_BOM_DB_PID="$pid" OH_MFG_BOM_DB_SOCKET="$socket" OH_MFG_BOM_DB_DATADIR="$data" OH_MFG_BOM_DB_RESOURCE_ROOT="$root" OH_MFG_BOM_DB_PORT="$port" OH_MFG_BOM_DB_NAME="$db" OH_MFG_BOM_DB_USER="$user" "$PHP_BIN" "$app_root/$probe"
        rc=$?
    elif [ "$prefix" = "OH_EXACT" ]; then
        OH_EXACT_REPO="$REPO" OH_EXACT_APP_ROOT="$app_root" OH_EXACT_DB_PID="$pid" OH_EXACT_DB_SOCKET="$socket" OH_EXACT_DB_DATADIR="$data" OH_EXACT_DB_RESOURCE_ROOT="$root" OH_EXACT_DB_PORT="$port" OH_EXACT_DB_NAME="$db" OH_EXACT_DB_USER="$user" "$PHP_BIN" "$app_root/$probe"
        rc=$?
    else
        OH_MFG_REPO="$REPO" OH_MFG_APP_ROOT="$app_root" OH_MFG_DB_PID="$pid" OH_MFG_DB_SOCKET="$socket" OH_MFG_DB_DATADIR="$data" OH_MFG_DB_RESOURCE_ROOT="$root" OH_MFG_DB_PORT="$port" OH_MFG_DB_NAME="$db" OH_MFG_DB_USER="$user" "$PHP_BIN" "$app_root/$probe"
        rc=$?
    fi
    set -e
    config_hash_after=$($PHP_BIN -r 'echo hash_file("sha256", $argv[1]);' "$app_root/storage/db_config.php")
    if [ "$config_hash_before" != "$config_hash_after" ]; then
        echo "SUITE FAIL $label: disposable configuration changed during probe" >&2
        rc=1
    fi
    if ! "$PHP_BIN" -r 'require $argv[1];$m=json_decode((string)file_get_contents($argv[3]),true,512,JSON_THROW_ON_ERROR);IsolatedSourceRootTestRunner::assertManifestUnchanged($argv[2],$m);' \
        "$REPO/scripts/testing/IsolatedSourceRootTestRunner.php" "$app_root" "$source_workspace/source-manifest.json"; then
        echo "SUITE FAIL $label: staged source manifest changed during probe" >&2
        rc=1
    fi
    elapsed=$(( $(date +%s) - started ))

    "$MYSQLADMIN_BIN" --no-defaults --protocol=SOCKET --socket="$socket" -uroot shutdown >/dev/null 2>&1 || true
    shutdown_done=1
    for _ in $(seq 1 60); do
        kill -0 "$pid" 2>/dev/null || break
        sleep .2
    done
    stopped=1
    kill -0 "$pid" 2>/dev/null && stopped=0
    socket_removed=1
    [ -S "$socket" ] && socket_removed=0
    cleanup

    installed_ok=1
    if [ -n "$INSTALLED_PID" ]; then
        kill -0 "$INSTALLED_PID" 2>/dev/null || installed_ok=0
        lsof -nP -iTCP:3306 -sTCP:LISTEN 2>/dev/null | grep -F 3306 >/dev/null || installed_ok=0
    fi
    if [ "$rc" -eq 0 ] && [ "$stopped" -eq 1 ] && [ "$socket_removed" -eq 1 ] && [ "$installed_ok" -eq 1 ]; then
        echo "SUITE PASS $label duration=${elapsed}s cleanup=true installed_service_preserved=true"
    else
        echo "SUITE FAIL $label duration=${elapsed}s rc=$rc stopped=$stopped socket_removed=$socket_removed installed_service_preserved=$installed_ok" >&2
        FAILED=$((FAILED + 1))
    fi
}

run_static "authorizer-safety" "scripts/testing/probe_disposable_db_execution_authorizer.php"
run_static "procurement-transaction-audit" "apps/Procurement/Tests/probe_transaction_contract_audit.php"
run_static "procurement-method-boundaries" "apps/Procurement/Tests/probe_order_receipt_method_boundaries.php"

case "${OH_TEST_ONLY:-}" in
    '')
        run_disposable_probe "procurement-concurrency" "OH_PROC" "apps/Procurement/Tests/probe_procurement_concurrency.php"
        run_disposable_probe "sbaio-sales-status" "OH_SBAIO" "apps/SBAIO/Tests/probe_behavioral_sales_status.php"
        run_disposable_probe "manufacturing-transaction" "OH_MFG" "apps/Manufacturing/Tests/probe_upstream_supply_transaction.php"
        run_disposable_probe "manufacturing-concurrency" "OH_MFG" "apps/Manufacturing/Tests/probe_upstream_supply_concurrency.php"
        ;;
    manufacturing-transaction)
        run_disposable_probe "manufacturing-transaction" "OH_MFG" "apps/Manufacturing/Tests/probe_upstream_supply_transaction.php"
        ;;
    sbaio-sales-status)
        run_disposable_probe "sbaio-sales-status" "OH_SBAIO" "apps/SBAIO/Tests/probe_behavioral_sales_status.php"
        ;;
    sbaio-lifecycle)
        run_disposable_probe "sbaio-lifecycle" "OH_SBAIO" "apps/SBAIO/Tests/probe_lifecycle_execution_boundary.php"
        ;;
    manufacturing-bom)
        run_disposable_probe "manufacturing-bom" "OH_MFG_BOM" "apps/Manufacturing/modules/Bom/Tests/probe_bom_contract.php"
        ;;
    exact-step-migrations)
        run_disposable_probe "exact-step-migrations" "OH_EXACT" "platform/Updates/tests/probe_controlled_exact_step_migrations.php"
        ;;
    *)
        echo "Unknown OH_TEST_ONLY value: ${OH_TEST_ONLY}" >&2
        exit 2
        ;;
esac

if [ -n "$INSTALLED_PID" ]; then
    kill -0 "$INSTALLED_PID" 2>/dev/null && echo "installed_mysql_pid=$INSTALLED_PID preserved"
fi

echo "REGRESSION RESULTS suites=$TOTAL failed=$FAILED"
[ "$FAILED" -eq 0 ]
