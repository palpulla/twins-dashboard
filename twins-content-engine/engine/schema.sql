CREATE TABLE IF NOT EXISTS clusters (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    name            TEXT NOT NULL UNIQUE,
    pillar          TEXT NOT NULL,
    service_type    TEXT,
    funnel_stage    TEXT,
    priority_score  INTEGER NOT NULL DEFAULT 5,
    notes           TEXT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_clusters_pillar ON clusters(pillar);

CREATE TABLE IF NOT EXISTS queries (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    cluster_id      INTEGER NOT NULL REFERENCES clusters(id) ON DELETE CASCADE,
    query_text      TEXT NOT NULL,
    phrasing_type   TEXT NOT NULL,
    geo_modifier    TEXT,
    source          TEXT NOT NULL,
    priority_score  INTEGER NOT NULL DEFAULT 5,
    notes           TEXT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (cluster_id, query_text)
);

CREATE INDEX IF NOT EXISTS idx_queries_cluster ON queries(cluster_id);

CREATE TABLE IF NOT EXISTS generated_content (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    cluster_id       INTEGER NOT NULL REFERENCES clusters(id) ON DELETE CASCADE,
    source_query_id  INTEGER NOT NULL REFERENCES queries(id) ON DELETE CASCADE,
    format           TEXT NOT NULL,
    content_path     TEXT NOT NULL,
    brief_path       TEXT,
    status           TEXT NOT NULL DEFAULT 'pending'
                         CHECK (status IN ('pending', 'approved', 'rejected', 'published')),
    model_used       TEXT NOT NULL,
    generated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at      DATETIME,
    notes            TEXT
);

CREATE INDEX IF NOT EXISTS idx_generated_cluster ON generated_content(cluster_id);
CREATE INDEX IF NOT EXISTS idx_generated_status ON generated_content(status);
