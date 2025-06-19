SELECT COUNT(*) as total_count
FROM article a
WHERE a.author_id = :authorId