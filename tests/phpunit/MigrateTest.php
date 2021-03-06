<?php

declare(strict_types=1);

namespace Keboola\AppProjectMigrate\Tests;

use Keboola\AppProjectMigrate\DockerRunnerClient;
use Keboola\AppProjectMigrate\Migrate;
use Keboola\Component\UserException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class MigrateTest extends TestCase
{

    public function testMigrateSuccess(): void
    {
        /** @var DockerRunnerClient|MockObject $sourceClientMock */
        $sourceClientMock = $this->createMock(DockerRunnerClient::class);

        /** @var DockerRunnerClient|MockObject $destClientMock */
        $destClientMock = $this->createMock(DockerRunnerClient::class);

        // generate credentials
        $sourceClientMock->expects($this->once())
            ->method('runSyncAction')
            ->with(
                Migrate::PROJECT_BACKUP_COMPONENT,
                'generate-read-credentials',
                [
                    'parameters' => [
                        'backupId' => null,
                    ],
                ]
            )
            ->willReturn([
                'backupId' => '123',
                'backupUri' => 'https://kbc.s3.amazonaws.com/data-takeout/us-east-1/4788/395904684/',
                'region' => 'us-east-1',
                'credentials' => [
                    'accessKeyId' => 'xxx',
                    'secretAccessKey' => 'yyy',
                    'sessionToken' => 'zzz',
                    'expiration' => '2018-05-23T10:49:02+00:00',
                ],
            ]);

        // run backup
        $sourceClientMock->expects($this->once())
            ->method('runJob')
            ->with(
                Migrate::PROJECT_BACKUP_COMPONENT,
                [
                    'configData' => [
                        'parameters' => [
                            'backupId' => '123',
                        ],
                    ],
                ]
            )
            ->willReturn([
                'id' => '222',
                'status' => 'success',
            ]);

        $sourceProjectUrl = 'https://connection.keboola.com';
        $sourceProjectToken = 'xyz';

        // run restore with credentials from step 1
        $destClientMock->expects($this->exactly(4))
            ->method('runJob')
            ->withConsecutive(
                // restore data
                [
                    Migrate::PROJECT_RESTORE_COMPONENT,
                    [
                        'configData' => [
                            'parameters' => [
                                'backupUri' => 'https://kbc.s3.amazonaws.com/data-takeout/us-east-1/4788/395904684/',
                                'accessKeyId' => 'xxx',
                                '#secretAccessKey' => 'yyy',
                                '#sessionToken' => 'zzz',
                                'useDefaultBackend' => true,
                            ],
                        ],
                    ],
                ],
                // restore snowflake writers
                [
                    Migrate::SNOWFLAKE_WRITER_MIGRATE_COMPONENT,
                    [
                        'configData' => [
                            'parameters' => [
                                'sourceKbcUrl' => $sourceProjectUrl,
                                '#sourceKbcToken' => $sourceProjectToken,
                            ],
                        ],
                    ],
                ],
                // restore good data writers
                [
                    Migrate::GOOD_DATA_WRITER_MIGRATE_COMPONENT,
                    [
                        'configData' => [
                            'parameters' => [
                                'sourceKbcUrl' => $sourceProjectUrl,
                                '#sourceKbcToken' => $sourceProjectToken,
                            ],
                        ],
                    ],
                ],
                // restore orchestrations
                [
                    Migrate::ORCHESTRATOR_MIGRATE_COMPONENT,
                    [
                        'configData' => [
                            'parameters' => [
                                'sourceKbcUrl' => $sourceProjectUrl,
                                '#sourceKbcToken' => $sourceProjectToken,
                            ],
                        ],
                    ],
                ]
            )->willReturn([
                'id' => '222',
                'status' => 'success',
            ]);


        $migrate = new Migrate(
            $sourceClientMock,
            $destClientMock,
            $sourceProjectUrl,
            $sourceProjectToken,
            new NullLogger()
        );
        $migrate->run();
    }

    public function testShouldFailOnSnapshotError(): void
    {
        /** @var DockerRunnerClient|MockObject $sourceClientMock */
        $sourceClientMock = $this->createMock(DockerRunnerClient::class);

        /** @var DockerRunnerClient|MockObject $destClientMock */
        $destClientMock = $this->createMock(DockerRunnerClient::class);

        // generate credentials
        $sourceClientMock->expects($this->once())
            ->method('runSyncAction')
            ->with(
                Migrate::PROJECT_BACKUP_COMPONENT,
                'generate-read-credentials',
                [
                    'parameters' => [
                        'backupId' => null,
                    ],
                ]
            )
            ->willReturn([
                'backupId' => '123',
                'backupUri' => 'https://kbc.s3.amazonaws.com/data-takeout/us-east-1/4788/395904684/',
                'region' => 'us-east-1',
                'credentials' => [
                    'accessKeyId' => 'xxx',
                    'secretAccessKey' => 'yyy',
                    'sessionToken' => 'zzz',
                    'expiration' => '2018-05-23T10:49:02+00:00',
                ],
            ]);

        $sourceClientMock->expects($this->once())
            ->method('runJob')
            ->with(
                Migrate::PROJECT_BACKUP_COMPONENT,
                [
                    'configData' => [
                        'parameters' => [
                            'backupId' => '123',
                        ],
                    ],
                ]
            )
            ->willReturn([
                'id' => '222',
                'status' => 'error',
                'result' => [
                    'message' => 'Cannot snapshot project',
                ],
            ]);

        $destClientMock->expects($this->never())
            ->method('runJob');

        $this->expectException(UserException::class);
        $this->expectExceptionMessageRegExp('/Cannot snapshot project/');
        $migrate = new Migrate(
            $sourceClientMock,
            $destClientMock,
            'xxx',
            'yyy',
            new NullLogger()
        );
        $migrate->run();
    }

    public function testShouldFailOnRestoreError(): void
    {
        /** @var DockerRunnerClient|MockObject $sourceClientMock */
        $sourceClientMock = $this->createMock(DockerRunnerClient::class);

        /** @var DockerRunnerClient|MockObject $destClientMock */
        $destClientMock = $this->createMock(DockerRunnerClient::class);

        // generate credentials
        $sourceClientMock->expects($this->once())
            ->method('runSyncAction')
            ->with(
                Migrate::PROJECT_BACKUP_COMPONENT,
                'generate-read-credentials',
                [
                    'parameters' => [
                        'backupId' => null,
                    ],
                ]
            )
            ->willReturn([
                'backupId' => '123',
                'backupUri' => 'https://kbc.s3.amazonaws.com/data-takeout/us-east-1/4788/395904684/',
                'region' => 'us-east-1',
                'credentials' => [
                    'accessKeyId' => 'xxx',
                    'secretAccessKey' => 'yyy',
                    'sessionToken' => 'zzz',
                    'expiration' => '2018-05-23T10:49:02+00:00',
                ],
            ]);

        $sourceClientMock->expects($this->once())
            ->method('runJob')
            ->with(
                Migrate::PROJECT_BACKUP_COMPONENT,
                [
                    'configData' => [
                        'parameters' => [
                            'backupId' => '123',
                        ],
                    ],
                ]
            )
            ->willReturn([
                'id' => '222',
                'status' => 'success',
            ]);

        $destClientMock->expects($this->any())
            ->method('runJob')
            ->willReturn([
                'id' => '222',
                'status' => 'error',
                'result' => [
                    'message' => 'Cannot restore project',
                ],
            ]);

        $this->expectException(UserException::class);
        $this->expectExceptionMessageRegExp('/Cannot restore project/');
        $migrate = new Migrate(
            $sourceClientMock,
            $destClientMock,
            'xxx',
            'yyy',
            new NullLogger()
        );
        $migrate->run();
    }
}
