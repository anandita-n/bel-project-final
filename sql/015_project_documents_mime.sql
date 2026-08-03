USE bel_pms;

ALTER TABLE project_documents
    ADD COLUMN mime_type VARCHAR(120) DEFAULT NULL AFTER size_bytes;
