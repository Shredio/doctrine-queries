<?php declare(strict_types = 1);

use Tests\Factory\EntityManagerFactory;

// The same database as the runtime part of RuntimeTypesTest, so PHPStan infers the types of its driver.
return EntityManagerFactory::create();
