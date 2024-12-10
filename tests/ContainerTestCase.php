<?php

namespace App\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolsException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class ContainerTestCase extends KernelTestCase
{
    private static bool $testDatabaseCreated = false;
    protected static ?Connection $connection = null;

    /**
     * @throws ToolsException|\Doctrine\DBAL\Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$connection) {
            self::bootKernel();
            self::$connection = self::getContainer()->get(Connection::class);
        }

        if (!self::$testDatabaseCreated) {
            $this->createTestDatabase();
            self::$testDatabaseCreated = true;
        }

        $this->truncateDatabaseTables();
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

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function truncateDatabaseTables(): void
    {
        $this->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=0');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        foreach ($entityManager->getConnection()->createSchemaManager()->listTableNames() as $tableName) {
            $this->getConnection()->executeStatement('TRUNCATE TABLE '.$tableName);
        }

        $this->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }

    protected function getConnection(): Connection
    {
        return self::$connection;
    }
}
