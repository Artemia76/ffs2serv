-- Support IPv6 (fiche 0003 de la suite) : un slot garde l'adresse IPv4 et l'adresse IPv6 observées par le portail,
-- chacune avec sa date de dernière observation. `ip` ne contient plus que de l'IPv4.

ALTER TABLE {slots}
    MODIFY ip VARCHAR(45) NULL,
    ADD COLUMN ip_seen_at DATETIME NULL AFTER ip,
    ADD COLUMN ip6 VARCHAR(45) NULL AFTER ip_seen_at,
    ADD COLUMN ip6_seen_at DATETIME NULL AFTER ip6;

UPDATE {slots} SET ip_seen_at = last_seen_at;
