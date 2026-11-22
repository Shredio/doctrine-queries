SELECT COUNT(*) as total_count
FROM Article a
WHERE a.author_id = :authorId