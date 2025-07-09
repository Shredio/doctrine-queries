<?php declare(strict_types = 1);

namespace Tests\Entity\Enum;

enum ArticleType: string
{

	case Blog = 'blog';
	case News = 'news';

}
