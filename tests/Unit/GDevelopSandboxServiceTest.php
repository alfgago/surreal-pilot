<?php

use App\Services\GDevelopSandboxService;
use App\Exceptions\GDevelopCliException;
use Illuminate\Support\Facades\Process;

describe('GDevelopSandboxService', function () {
    beforeEach(function () {
        $this->service = new GDevelopSandboxService();
    });

    describe('executeSandboxedCommand', function () {
        it('executes allowed commands successfully', function () {
            Process::fake([
                '*' => Process::result(
                    output: 'Command executed successfully',
                    exitCode: 0
                )
            ]);

            $workingDir = storage_path('gdevelop/test');
            if (!is_dir($workingDir)) {
                mkdir($workingDir, 0755, true);
            }

            $result = $this->service->executeSandboxedCommand(
                'export',
                ['format' => 'html5', 'output' => 'build'],
                $workingDir
            );

            expect($result['success'])->toBeTrue()
                ->and($result['output'])->toBe('Command executed successfully')
                ->and($result['exit_code'])->toBe(0);

            // Cleanup
            if (is_dir($workingDir)) {
                rmdir($workingDir);
            }
        });

        it('throws exception for disallowed commands', function () {
            $workingDir = storage_path('gdevelop/test');

            expect(fn() => $this->service->executeSandboxedCommand(
                'rm',
                ['-rf', '/'],
                $workingDir
            ))->toThrow(GDevelopCliException::class, 'Command \'rm\' is not allowed');
        });

        it('throws exception for invalid working directory', function () {
            expect(fn() => $this->service->executeSandboxedCommand(
                'export',
                [],
                '/invalid/directory'
            ))->toThrow(GDevelopCliException::class, 'Working directory does not exist');
        });

        it('throws exception for directory traversal attempts', function () {
            expect(fn() => $this->service->executeSandboxedCommand(
                'export',
                [],
                storage_path('gdevelop/../../../etc')
            ))->toThrow(GDevelopCliException::class, 'Directory traversal detected');
        });

        it('sanitizes command arguments', function () {
            Process::fake([
                '*' => Process::result(output: 'Success', exitCode: 0)
            ]);

            $workingDir = storage_path('gdevelop/test');
            if (!is_dir($workingDir)) {
                mkdir($workingDir, 0755, true);
            }

            $result = $this->service->executeSandboxedCommand(
                'export',
                [
                    'format' => 'html5; rm -rf /',  // Malicious content
                    'output' => 'build`whoami`'     // Command injection attempt
                ],
                $workingDir
            );

            // Should execute without the malicious parts
            expect($result['success'])->toBeTrue();

            // Cleanup
            if (is_dir($workingDir)) {
                rmdir($workingDir);
            }
        });

        it('handles command failures properly', function () {
            Process::fake([
                '*' => Process::result(
                    output: '',
                    errorOutput: 'Command failed',
                    exitCode: 1
                )
            ]);

            $workingDir = storage_path('gdevelop/test');
            if (!is_dir($workingDir)) {
                mkdir($workingDir, 0755, true);
            }

            expect(fn() => $this->service->executeSandboxedCommand(
                'export',
                [],
                $workingDir
            ))->toThrow(GDevelopCliException::class, 'Command failed with exit code 1');

            // Cleanup
            if (is_dir($workingDir)) {
                rmdir($workingDir);
            }
        });
    });

    describe('createIsolatedWorkspace', function () {
        it('creates isolated workspace directory', function () {
            $sessionId = 'test-session-' . uniqid();
            
            $workspacePath = $this->service->createIsolatedWorkspace($sessionId);
            
            expect($workspacePath)->toContain($sessionId)
                ->and(is_dir($workspacePath))->toBeTrue();

            // Cleanup
            $this->service->cleanupIsolatedWorkspace($sessionId);
        });

        it('sets proper permissions on workspace', function () {
            $sessionId = 'test-session-' . uniqid();
            
            $workspacePath = $this->service->createIsolatedWorkspace($sessionId);
            
            $permissions = substr(sprintf('%o', fileperms($workspacePath)), -4);
            expect($permissions)->toBe('0755');

            // Cleanup
            $this->service->cleanupIsolatedWorkspace($sessionId);
        });
    });

    describe('cleanupIsolatedWorkspace', function () {
        it('removes isolated workspace directory', function () {
            $sessionId = 'test-session-' . uniqid();
            
            // Create workspace
            $workspacePath = $this->service->createIsolatedWorkspace($sessionId);
            expect(is_dir($workspacePath))->toBeTrue();

            // Create some test files
            file_put_contents($workspacePath . '/test.txt', 'test content');
            mkdir($workspacePath . '/subdir');
            file_put_contents($workspacePath . '/subdir/nested.txt', 'nested content');

            // Cleanup
            $this->service->cleanupIsolatedWorkspace($sessionId);
            
            expect(is_dir($workspacePath))->toBeFalse();
        });
    });

    describe('argument sanitization', function () {
        it('removes shell metacharacters from arguments', function () {
            $reflection = new ReflectionClass($this->service);
            $method = $reflection->getMethod('sanitizeArguments');
            $method->setAccessible(true);

            $dangerousArgs = [
                'format' => 'html5|rm -rf /',
                'output' => 'build`whoami`',
                'name' => 'game; cat /etc/passwd',
                'valid' => 'normal-value_123'
            ];

            $result = $method->invoke($this->service, $dangerousArgs);

            expect($result['format'])->toBe('html5rm -rf ')
                ->and($result['output'])->toBe('buildwhoami')
                ->and($result['name'])->toBe('game cat /etc/passwd')
                ->and($result['valid'])->toBe('normal-value_123');
        });

        it('limits argument length', function () {
            $reflection = new ReflectionClass($this->service);
            $method = $reflection->getMethod('sanitizeArgumentValue');
            $method->setAccessible(true);

            $longString = str_repeat('a', 1500);
            $result = $method->invoke($this->service, $longString);

            expect(strlen($result))->toBe(1000);
        });

        it('handles non-string arguments', function () {
            $reflection = new ReflectionClass($this->service);
            $method = $reflection->getMethod('sanitizeArgumentValue');
            $method->setAccessible(true);

            expect($method->invoke($this->service, 123))->toBe('123')
                ->and($method->invoke($this->service, 45.67))->toBe('45.67')
                ->and($method->invoke($this->service, []))->toBeNull()
                ->and($method->invoke($this->service, new stdClass()))->toBeNull();
        });
    });
});