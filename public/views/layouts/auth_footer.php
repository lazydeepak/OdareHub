  </main>

  <footer class="auth-footer muted">
    <?= e(t('app.name')) ?> v<?= e(APP_VERSION) ?> © <?= date('Y') ?>
  </footer>
</div>

<script>
(function () {
  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.classList.contains('auth-form')) {
      return;
    }

    var submitter = event.submitter instanceof HTMLButtonElement
      ? event.submitter
      : form.querySelector('button.auth-submit[type="submit"]');

    if (!(submitter instanceof HTMLButtonElement) || !submitter.classList.contains('auth-submit')) {
      return;
    }

    if (submitter.dataset.authLoadingApplied === '1') {
      event.preventDefault();
      return;
    }

    submitter.dataset.authLoadingApplied = '1';
    submitter.disabled = true;
    submitter.setAttribute('aria-busy', 'true');
    submitter.classList.add('is-loading');

    var idleLabel = submitter.querySelector('.auth-submit-label');
    var loadingLabel = submitter.querySelector('.auth-submit-loading');
    if (idleLabel instanceof HTMLElement) {
      idleLabel.hidden = true;
      idleLabel.setAttribute('aria-hidden', 'true');
    }
    if (loadingLabel instanceof HTMLElement) {
      loadingLabel.hidden = false;
      loadingLabel.setAttribute('aria-hidden', 'false');
    }
  });
})();
</script>

</body>
</html>