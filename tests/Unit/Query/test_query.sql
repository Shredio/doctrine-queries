SELECT a.id, a.title, au.name as author_name
FROM Article a
JOIN Author au ON a.author_id = au.id
WHERE a.id = :articleId