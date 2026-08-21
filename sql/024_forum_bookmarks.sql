
CREATE TABLE IF NOT EXISTS forum_bookmarks (
    user_id INT NOT NULL,
    question_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, question_id),
    CONSTRAINT fk_fb_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_fb_question FOREIGN KEY (question_id) REFERENCES forum_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;
