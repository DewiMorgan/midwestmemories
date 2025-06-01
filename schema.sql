create table `midmem_file_queue`
(
    id            INT AUTO_INCREMENT PRIMARY KEY,
    file_name     TEXT                                             NOT NULL COMMENT '',
    full_path     TEXT                                             NOT NULL
        COMMENT 'Dropbox path_display with leading slashes removed',
    sync_status   ENUM ('NEW', 'ERROR', 'DOWNLOADED', 'PROCESSED') NOT NULL DEFAULT 'NEW'
        COMMENT 'How the sync of this queued file has progressed.',
    error_message TEXT                                             NOT NULL DEFAULT ''
        COMMENT 'Description of any error encountered',
    file_hash     TEXT                                             NOT NULL DEFAULT ''
        COMMENT 'Hash of file, to tell if it has changed.',
    UNIQUE INDEX (full_path)
) ENGINE = InnoDB
  COLLATE = utf8mb4_unicode_ci,
    COMMENT 'Files that have been queued or processed through Dropbox. Lightweight, minimal metadata.';

CREATE TABLE `midmem_dropbox_users`
(
    user_id           INT      NOT NULL PRIMARY KEY COMMENT 'Who to log into dropbox as.',
    cursor_id         TEXT     NOT NULL COMMENT 'Their current cursor for reading the DB root folder.',
    webhook_timestamp DATETIME NULL COMMENT 'When the web hook last used this cursor.'
) ENGINE = InnoDB
  COLLATE = utf8mb4_unicode_ci
    COMMENT 'list of Dropbox users that we have cursors for. We only actually use the one.';

CREATE TABLE `midmem_visitors`
(
    id             INT AUTO_INCREMENT PRIMARY KEY,
    request        TEXT         NOT NULL COMMENT 'The request URI',
    main_ip        VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'The most significant IP of the connection.',
    all_ips_string TEXT         NOT NULL COMMENT 'Comma separated list of all IPs for this connection',
    user           TEXT         NOT NULL COMMENT 'Associated username, if any',
    agent          TEXT         NOT NULL COMMENT 'User agent string',
    date_created   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When this record was created.',
    date_modified  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        COMMENT 'When this record was last edited.'
) ENGINE = InnoDB
  COLLATE = utf8mb4_unicode_ci
    COMMENT 'Record of who fetched what URIs, from where.';

CREATE TABLE `midmem_metadata`
(
    id            INT AUTO_INCREMENT PRIMARY KEY,
    is_folder     TINYINT      NOT NULL DEFAULT false COMMENT 'true=folder, false=file. Could be enum?',
    display_name  VARCHAR(255) NOT NULL COMMENT 'e.g. Pic of my butt, or Folder of butt pics',
    source        VARCHAR(255) NULL COMMENT 'e.g. Facebook. Null = unknown.',
    written_notes TEXT         NOT NULL COMMENT 'Whatever was found written with the source.',
    visitor_notes TEXT         NOT NULL COMMENT 'Whatever anyone writes about it.',
    location      TEXT         NOT NULL COMMENT 'Address, or whatever.',
    date_start    DATETIME     NULL COMMENT 'Null = unknown.',
    date_end      DATETIME     NULL COMMENT 'Null = unknown.',
    fk_file       INT          NOT NULL COMMENT 'FK to midmem_file_queue.id',
    date_created  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When this record was created.',
    date_modified DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        COMMENT 'When this record was last edited.',
    FOREIGN KEY (fk_file) REFERENCES midmem_file_queue (id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE = InnoDB
  COLLATE = utf8mb4_unicode_ci
    COMMENT 'The metadata about files and folders, parsed in from config files and editable through the web.';

CREATE TABLE `midmem_characters`
(
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(255)                                NOT NULL COMMENT 'Full name of character.',
    gender     ENUM ('UNKNOWN', 'MALE', 'FEMALE', 'OTHER') NOT NULL DEFAULT 'UNKNOWN' COMMENT 'Identifies as.',
    user_notes TEXT                                        NOT NULL COMMENT 'Extra info on character.'
) ENGINE = InnoDB
  COLLATE = utf8mb4_unicode_ci
    COMMENT 'Named characters (people, animals, dolls, etc) who might be subjects or photographers.';

CREATE TABLE `midmem_related_characters`
(
    id           INT AUTO_INCREMENT PRIMARY KEY,
    fk_subject   INT                                           NOT NULL COMMENT 'FK to the subject of relationship.',
    fk_related   INT                                           NOT NULL COMMENT 'FK to the person they are related to.',
    relationship ENUM ('unknown', 'parent', 'child', 'spouse') NOT NULL DEFAULT 'UNKNOWN'
        COMMENT 'Relationship of subject to related person.',
    user_notes   TEXT                                          NOT NULL COMMENT 'Extra relationship info.',
    FOREIGN KEY (fk_subject) REFERENCES midmem_characters (id) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (fk_related) REFERENCES midmem_characters (id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE = InnoDB
  COLLATE = utf8mb4_unicode_ci
    COMMENT 'A mapping of relationships between pairs of characters.';

CREATE TABLE `midmem_people_files`
(
    id           INT AUTO_INCREMENT PRIMARY KEY,
    fk_person    INT                                         NOT NULL COMMENT 'FK to midmem_people.id',
    fk_file      INT                                         NOT NULL COMMENT 'FK to midmem_file_queue.id',
    relationship ENUM ('UNKNOWN', 'PHOTOGRAPHER', 'SUBJECT') NOT NULL DEFAULT 'UNKNOWN'
        COMMENT 'Relationship of subject to file.',
    FOREIGN KEY (fk_person) REFERENCES midmem_characters (id) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (fk_file) REFERENCES midmem_file_queue (id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE = InnoDB
  COLLATE = utf8mb4_unicode_ci
    COMMENT 'A mapping of relationships between characters and the files they took, were in, etc.';

CREATE TABLE `midmem_keywords`
(
    id       INT AUTO_INCREMENT PRIMARY KEY,
    name     VARCHAR(255) NOT NULL COMMENT 'The keyword',
    fk_alias INT COMMENT 'If this is an alias, point to the preferred keyword. Enforce single level!',
    -- ON DELETE SET NULL means deleting a root alias ungroups all its aliased children.
    FOREIGN KEY (fk_alias) REFERENCES midmem_keywords (id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE = InnoDB
  COLLATE = utf8mb4_unicode_ci
    COMMENT 'List of keywords for labelling files. Keywords may be aliases of others.';

CREATE TABLE `midmem_keyword_files`
(
    id         INT AUTO_INCREMENT PRIMARY KEY,
    fk_keyword INT NOT NULL COMMENT 'FK to midmem_keywords.id.',
    fk_file    INT NOT NULL COMMENT 'FK to midmem_file_queue.id.',
    FOREIGN KEY (fk_keyword) REFERENCES midmem_keywords (id) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (fk_file) REFERENCES midmem_file_queue (id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE = InnoDB
  COLLATE = utf8mb4_unicode_ci
    COMMENT 'A mapping of keywords to files.';

CREATE TABLE `midmem_comments`
(
    `id`           int(11)      NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `fk_file`      int(11)      NOT NULL COMMENT 'fk to midmem_file_queue',
    `hidden`       tinyint(1)   NOT NULL DEFAULT 0 COMMENT 'True if the comment is hidden by admin action, editing...',
    `sequence`     int(11)      NOT NULL DEFAULT 1 COMMENT 'Their order within the file comments. For edits etc.',
    `date_created` datetime     NOT NULL DEFAULT current_timestamp(),
    `user`         varchar(255) NOT NULL COMMENT 'Who wrote it - should be fk int.',
    `body_text`    text         NOT NULL COMMENT 'The content of their comment',
    FOREIGN KEY (`fk_file`) REFERENCES midmem_file_queue (id) ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE KEY `file_id_index` (`fk_file`),
    UNIQUE KEY `user_index` (`user`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci
    COMMENT 'Comments left by visitors';
