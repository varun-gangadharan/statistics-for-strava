<?php

namespace App\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolsException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class ContainerTestCase extends KernelTestCase
{
    protected static ?Connection $connection = null;

    /**
     * @throws ToolsException
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$connection) {
            self::bootKernel();
            self::$connection = self::getContainer()->get(Connection::class);
        }

        $this->createTestDatabase();
    }

    /**
     * @throws ToolsException
     */
    private function createTestDatabase(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($entityManager);
        $classes = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($classes);
    }

    protected function getConnection(): Connection
    {
        return self::$connection;
    }
}
