SELECT a.id, a.title, au.name as author_name
FROM article a
JOIN author au ON a.author_id = au.id
WHERE a.id IN (:articleIds)