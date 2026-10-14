-- OH-AUTO1-022 known disposable-schema fixture.
-- This file is a contract only; the current harness never executes it.
-- A future runner must execute it only after disposable identity proof passes.

CREATE TABLE oh_test_parents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    marker CHAR(32) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_oh_test_parent_marker (marker)
) ENGINE=InnoDB;

CREATE TABLE oh_test_children (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id BIGINT UNSIGNED NOT NULL,
    marker CHAR(32) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_oh_test_child_marker (marker),
    CONSTRAINT fk_oh_test_child_parent
        FOREIGN KEY (parent_id) REFERENCES oh_test_parents (id)
) ENGINE=InnoDB;
