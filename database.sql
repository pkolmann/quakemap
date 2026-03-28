CREATE TABLE IF NOT EXISTS `quakes`
(
    quake_id     BIGINT AUTO_INCREMENT PRIMARY KEY,
    source       VARCHAR(10)  NOT NULL,
    source_id    VARCHAR(255) NOT NULL,
    time         DOUBLE       NOT NULL,
    latitude     DOUBLE       NOT NULL,
    longitude    DOUBLE       NOT NULL,
    depth        DOUBLE       NULL,
    magnitude_ml DOUBLE       NULL,
    location     VARCHAR(255) NULL,
    region       VARCHAR(255) NULL,
    comment      VARCHAR(255) NULL,
    url          VARCHAR(255) NULL,
    author       VARCHAR(255) NULL
);

create index if not exists quakes_source_source_id_index
    on quakes (source, source_id);
create index if not exists quakes_time_index
    on quakes (time);


CREATE TABLE IF NOT EXISTS magnitudes
(
    mag_id   BIGINT AUTO_INCREMENT PRIMARY KEY,
    quake_id BIGINT      NOT NULL,
    type     VARCHAR(10) NOT NULL,
    value    DOUBLE      NOT NULL,
    FOREIGN KEY (quake_id) REFERENCES quakes (quake_id) ON DELETE CASCADE
);