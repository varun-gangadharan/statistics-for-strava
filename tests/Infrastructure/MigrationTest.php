<?php

namespace App\Tests\Infrastructure;

use App\Infrastructure\ValueObject\String\KernelProjectDir;
use App\Tests\ContainerTestCase;
use Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Console\Input\ArrayInput;

class MigrationTest extends ContainerTestCase
{
    public function testItShouldContainAllMigrations(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $this->prepareDatabaseSchema($entityManager);
        $this->runMigrations($entityManager);

        $schemaTool = new SchemaTool($entityManager);
        $statements = $schemaTool->getUpdateSchemaSql($entityManager->getMetadataFactory()->getAllMetadata());

        $key = array_search('DROP TABLE migration_versions', $statements);
        if (false !== $key) {
            unset($statements[$key]);
        }

        $this->assertEmpty($statements, 'MISSING MIGRATION: '.PHP_EOL.implode(PHP_EOL.PHP_EOL, $statements));
    }

    private function prepareDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        // Make sure db is empty before we run migrations.
        $schemaTool = new SchemaTool($entityManager);
        $classes = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($classes);
        $this->getConnection()->executeQuery('DROP table IF EXISTS migration_versions');
    }

    private function runMigrations(EntityManagerInterface $entityManager): void
    {
        $kernelProjectDir = $this->getContainer()->get(KernelProjectDir::class);
        $config = new ConfigurationArray([
            'migrations_paths' => ['DoctrineMigrations' => $kernelProjectDir.'/migrations'],
            'table_storage' => [
                'table_name' => 'migration_versions',
            ],
            'transactional' => false,
        ]);

        $loader = new ExistingEntityManager($entityManager);
        $dependencyFactory = DependencyFactory::fromEntityManager($config, $loader);

        $version = $dependencyFactory->getVersionAliasResolver()->resolveVersionAlias('latest');
        $planCalculator = $dependencyFactory->getMigrationPlanCalculator();
        $plan = $planCalculator->getPlanUntilVersion($version);
        $migrator = $dependencyFactory->getMigrator();
        $migratorConfigurationFactory = $dependencyFactory->getConsoleInputMigratorConfigurationFactory();
        $migratorConfiguration = $migratorConfigurationFactory->getMigratorConfiguration(new ArrayInput([]));

        $dependencyFactory->getMetadataStorage()->ensureInitialized();
        $migrator->migrate($plan, $migratorConfiguration);
    }
}
