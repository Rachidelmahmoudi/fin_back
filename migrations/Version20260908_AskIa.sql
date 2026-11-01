-- FinPharma — Ask IA (§25)
CREATE TABLE ask_ia_session (
    id UUID NOT NULL,
    user_id UUID NOT NULL,
    medicine_id UUID NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'IN_PROGRESS', -- IN_PROGRESS|COMPLETED
    qa_history JSON NOT NULL DEFAULT '[]',
    conclusion_text TEXT DEFAULT NULL,
    current_question TEXT DEFAULT NULL,
    created_at TIMESTAMP(0) NOT NULL,
    PRIMARY KEY (id)
);
CREATE INDEX idx_ask_ia_user ON ask_ia_session (user_id);
ALTER TABLE ask_ia_session ADD CONSTRAINT fk_ask_ia_user FOREIGN KEY (user_id) REFERENCES app_user (id);
ALTER TABLE ask_ia_session ADD CONSTRAINT fk_ask_ia_medicine FOREIGN KEY (medicine_id) REFERENCES medicine (id);
