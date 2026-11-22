<?php declare(strict_types = 1);

use Tests\Factory\EntityManagerFactory;

// The expected types in the PHPStan tests are the ones of SQLite, whatever database the suite runs against.
return EntityManagerFactory::createInMemory();
