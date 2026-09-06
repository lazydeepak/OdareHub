-- Hospitality Slice 5: guest deactivation support (repo safety convention:
-- soft status transitions instead of hard deletes).
-- Additive-only change to the Slice 2 schema.

ALTER TABLE hosp_guests
    ADD COLUMN guest_status VARCHAR(30) NOT NULL DEFAULT 'active';
