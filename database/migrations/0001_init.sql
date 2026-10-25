-- Schéma initial du portail FFS2play
-- Les noms entre accolades reçoivent le préfixe de tables choisi à l'installation.
-- Toutes les dates sont en UTC.

CREATE TABLE {users} (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    callsign VARCHAR(32) NOT NULL,
    email VARCHAR(191) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('pilot','admin') NOT NULL DEFAULT 'pilot',
    status ENUM('pending','active','banned') NOT NULL DEFAULT 'pending',
    email_verified_at DATETIME NULL,
    preferred_server_id INT UNSIGNED NULL,
    global_mode TINYINT(1) NOT NULL DEFAULT 0,
    team_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    last_login_at DATETIME NULL,
    UNIQUE KEY uq_callsign (callsign),
    UNIQUE KEY uq_email (email),
    KEY idx_team (team_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {user_tokens} (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type ENUM('verify_email','reset_password') NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_token (token_hash),
    KEY idx_user_type (user_id, type),
    CONSTRAINT {fk_tokens_user} FOREIGN KEY (user_id) REFERENCES {users} (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {login_attempts} (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    scope VARCHAR(20) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    identifier VARCHAR(191) NOT NULL,
    attempted_at DATETIME NOT NULL,
    KEY idx_ip (scope, ip, attempted_at),
    KEY idx_identifier (scope, identifier, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {servers} (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(64) NOT NULL,
    description VARCHAR(500) NOT NULL DEFAULT '',
    is_private TINYINT(1) NOT NULL DEFAULT 0,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    owner_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_name (name),
    CONSTRAINT {fk_servers_owner} FOREIGN KEY (owner_id) REFERENCES {users} (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {server_members} (
    server_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    role ENUM('pending','member','admin') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL,
    PRIMARY KEY (server_id, user_id),
    KEY idx_user (user_id),
    CONSTRAINT {fk_members_server} FOREIGN KEY (server_id) REFERENCES {servers} (id) ON DELETE CASCADE,
    CONSTRAINT {fk_members_user} FOREIGN KEY (user_id) REFERENCES {users} (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {teams} (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(64) NOT NULL,
    leader_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_name (name),
    CONSTRAINT {fk_teams_leader} FOREIGN KEY (leader_id) REFERENCES {users} (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {team_requests} (
    team_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (team_id, user_id),
    KEY idx_user (user_id),
    CONSTRAINT {fk_requests_team} FOREIGN KEY (team_id) REFERENCES {teams} (id) ON DELETE CASCADE,
    CONSTRAINT {fk_requests_user} FOREIGN KEY (user_id) REFERENCES {users} (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE {users}
    ADD CONSTRAINT {fk_users_server} FOREIGN KEY (preferred_server_id) REFERENCES {servers} (id) ON DELETE SET NULL,
    ADD CONSTRAINT {fk_users_team} FOREIGN KEY (team_id) REFERENCES {teams} (id) ON DELETE SET NULL;

CREATE TABLE {slots} (
    user_id INT UNSIGNED NOT NULL PRIMARY KEY,
    server_id INT UNSIGNED NOT NULL,
    global_mode TINYINT(1) NOT NULL DEFAULT 0,
    ip VARCHAR(45) NOT NULL,
    local_ip VARCHAR(512) NOT NULL DEFAULT 'na',
    port INT UNSIGNED NOT NULL,
    aes_key VARCHAR(255) NOT NULL,
    key_hash CHAR(64) NOT NULL,
    client_version VARCHAR(32) NOT NULL DEFAULT '',
    sim VARCHAR(32) NOT NULL DEFAULT '',
    connection_id INT UNSIGNED NULL,
    connected_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    UNIQUE KEY uq_key (key_hash),
    KEY idx_seen (last_seen_at),
    CONSTRAINT {fk_slots_user} FOREIGN KEY (user_id) REFERENCES {users} (id) ON DELETE CASCADE,
    CONSTRAINT {fk_slots_server} FOREIGN KEY (server_id) REFERENCES {servers} (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {positions} (
    user_id INT UNSIGNED NOT NULL PRIMARY KEY,
    latitude DOUBLE NOT NULL,
    longitude DOUBLE NOT NULL,
    heading SMALLINT NOT NULL DEFAULT 0,
    altitude INT NOT NULL DEFAULT 0,
    ground_speed SMALLINT NOT NULL DEFAULT 0,
    ias SMALLINT NOT NULL DEFAULT 0,
    squawk SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    on_ground TINYINT(1) NOT NULL DEFAULT 1,
    phase VARCHAR(20) NOT NULL DEFAULT '',
    aircraft VARCHAR(128) NOT NULL DEFAULT '',
    category VARCHAR(64) NOT NULL DEFAULT '',
    updated_at DATETIME NOT NULL,
    CONSTRAINT {fk_positions_user} FOREIGN KEY (user_id) REFERENCES {users} (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {connections} (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    server_id INT UNSIGNED NULL,
    client_version VARCHAR(32) NOT NULL DEFAULT '',
    sim VARCHAR(32) NOT NULL DEFAULT '',
    connected_at DATETIME NOT NULL,
    disconnected_at DATETIME NULL,
    KEY idx_user (user_id, connected_at),
    CONSTRAINT {fk_connections_user} FOREIGN KEY (user_id) REFERENCES {users} (id) ON DELETE CASCADE,
    CONSTRAINT {fk_connections_server} FOREIGN KEY (server_id) REFERENCES {servers} (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {flights} (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    server_id INT UNSIGNED NULL,
    aircraft VARCHAR(128) NOT NULL DEFAULT '',
    sim VARCHAR(32) NOT NULL DEFAULT '',
    status ENUM('in_progress','completed','aborted') NOT NULL DEFAULT 'in_progress',
    started_at DATETIME NOT NULL,
    ended_at DATETIME NULL,
    updated_at DATETIME NOT NULL,
    dep_latitude DOUBLE NOT NULL,
    dep_longitude DOUBLE NOT NULL,
    arr_latitude DOUBLE NULL,
    arr_longitude DOUBLE NULL,
    max_altitude INT NOT NULL DEFAULT 0,
    distance_nm DOUBLE NOT NULL DEFAULT 0,
    KEY idx_user (user_id, started_at),
    KEY idx_status (status, updated_at),
    CONSTRAINT {fk_flights_user} FOREIGN KEY (user_id) REFERENCES {users} (id) ON DELETE CASCADE,
    CONSTRAINT {fk_flights_server} FOREIGN KEY (server_id) REFERENCES {servers} (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {track_points} (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    flight_id INT UNSIGNED NULL,
    latitude DOUBLE NOT NULL,
    longitude DOUBLE NOT NULL,
    altitude INT NOT NULL DEFAULT 0,
    recorded_at DATETIME NOT NULL,
    KEY idx_user (user_id, recorded_at),
    KEY idx_flight (flight_id),
    CONSTRAINT {fk_points_user} FOREIGN KEY (user_id) REFERENCES {users} (id) ON DELETE CASCADE,
    CONSTRAINT {fk_points_flight} FOREIGN KEY (flight_id) REFERENCES {flights} (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {settings} (
    name VARCHAR(64) NOT NULL PRIMARY KEY,
    value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
