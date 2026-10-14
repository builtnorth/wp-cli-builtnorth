<?php

declare(strict_types=1);

namespace BuiltNorth\CLI\Tests\Support;

use ReflectionMethod;
use ReflectionProperty;
use WP_CLI;
use WP_CLI\Loggers\Execution;
use WP_Mock;
use WP_Mock\Tools\TestCase;

/**
 * Base test case for code that reports through WP_CLI.
 *
 * Installs WP-CLI's own Execution logger so success/warning/error/debug messages
 * are captured instead of printed, and turns on WP_CLI's exit capture so
 * WP_CLI::error() throws WP_CLI\ExitException rather than ending the process.
 * The runner gets a minimal config (debug on) because a bare Runner's config is
 * null, which the logger's debug() reads.
 */
abstract class CliTestCase extends TestCase
{
	protected Execution $logger;

	private mixed $previous_runner_config = null;

	public function setUp(): void
	{
		parent::setUp();
		WP_Mock::setUp();

		$this->logger = new Execution();
		WP_CLI::set_logger($this->logger);
		$this->set_capture_exit(true);

		$config = new ReflectionProperty(WP_CLI::get_runner(), 'config');
		$config->setAccessible(true);
		$this->previous_runner_config = $config->getValue(WP_CLI::get_runner());
		$config->setValue(WP_CLI::get_runner(), ['debug' => true]);
	}

	public function tearDown(): void
	{
		$config = new ReflectionProperty(WP_CLI::get_runner(), 'config');
		$config->setAccessible(true);
		$config->setValue(WP_CLI::get_runner(), $this->previous_runner_config);

		$this->set_capture_exit(false);
		WP_CLI::set_logger(null);

		WP_Mock::tearDown();
		parent::tearDown();
	}

	/**
	 * Call a non-public method on an object.
	 *
	 * @param object       $object Target instance.
	 * @param string       $method Method name.
	 * @param array<mixed> $args   Arguments.
	 */
	protected function invoke(object $object, string $method, array $args = []): mixed
	{
		$reflection = new ReflectionMethod($object, $method);
		$reflection->setAccessible(true);

		return $reflection->invokeArgs($object, $args);
	}

	/**
	 * Run a callable while capturing what it echoes (WP_CLI::line echoes directly).
	 */
	protected function capture_output(callable $callback): mixed
	{
		ob_start();
		try {
			return $callback();
		} finally {
			ob_end_clean();
		}
	}

	private function set_capture_exit(bool $value): void
	{
		$property = new ReflectionProperty(WP_CLI::class, 'capture_exit');
		$property->setAccessible(true);
		$property->setValue(null, $value);
	}
}
