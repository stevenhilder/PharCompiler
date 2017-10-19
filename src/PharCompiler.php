<?php declare(strict_types = 1);

//
// SevenPercent/PharCompiler - Compiles a CLI PHP project into a self-executing
// Phar archive
//
// Written in 2017 by Steven Hilder <steven.hilder@sevenpercent.solutions>
//
// To the extent possible under law, the author(s) have dedicated all copyright
// and related and neighboring rights to this software to the public domain
// worldwide. This software is distributed without any warranty.
//
// You should have received a copy of the CC0 Public Domain Dedication along with
// this software. If not, see <http://creativecommons.org/publicdomain/zero/1.0/>.
//

namespace SevenPercent;

use Exception;
use FilesystemIterator;
use Phar;
use PharException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class PharCompiler {

	public static function compile(string $executable, string $buildDirectory, array $includeDirectories = []): void {

		// Check that the Phar module has write permission
		if (boolval(ini_get('phar.readonly')) === TRUE) {
			throw new Exception('INI setting phar.readonly enabled');

		// Validate $executable parameter
		} elseif (!file_exists($executable)) {
			throw new Exception("File does not exist: $executable");
		} elseif (!is_file($executable)) {
			throw new Exception("Specified path is not a file: $executable");
		} elseif (!is_readable($executable)) {
			throw new Exception("File not readable: $executable");
		} else {

			// Validate $buildDirectory parameter
			if (file_exists($buildDirectory)) {
				if (!is_dir($buildDirectory)) {
					throw new Exception("Specified path is not a directory: $buildDirectory");
				} elseif (!is_writable($buildDirectory)) {
					throw new Exception("Directory not writable: $buildDirectory");
				}
			} elseif (!mkdir($buildDirectory, 0777, TRUE)) {
				throw new Exception("Unable to create directory: $buildDirectory");
			}

			// Validate $includeDirectories parameter
			foreach ($includeDirectories as $includeDirectory) {
				if (!is_string($includeDirectory)) {
					throw new Exception("Non-string value passsed as directory name");
				} elseif (!file_exists($includeDirectory)) {
					throw new Exception("Directory does not exist: $includeDirectory");
				} elseif (!is_dir($includeDirectory)) {
					throw new Exception("Specified path is not a directory: $includeDirectory");
				}
			}

			// Create a new Phar archive in the specified build directory
			$basename = basename($executable);
			try {
				$phar = new Phar("$buildDirectory/$basename.phar", 0, "$basename.phar");
			} catch (Exception $exception) {
				throw new Exception('Error creating Phar archive');
			}

			// Recursively add .php files from the specified include directories
			$phar->startBuffering();
			foreach ($includeDirectories as $includeDirectory) {
				$pathOffset = strlen(dirname(realpath($includeDirectory))) + 1;
				foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($includeDirectory, FilesystemIterator::SKIP_DOTS)) as $includeFile) {
					if ($includeFile->getExtension() === 'php' && ($fileRealPath = $includeFile->getRealPath()) !== __FILE__) {
						try {
							$phar->addFile($fileRealPath, substr($fileRealPath, $pathOffset));
						} catch (PharException $exception) {
							throw new Exception("Error adding file: $fileRealPath");
						}
					}
				}
			}

			// Add the root executable
			try {
				$phar->addFromString($basename, preg_replace('{^#!(?:/usr)?(?:/local)?/bin(?:/env\\s+)php\\s*}', '', file_get_contents($executable)));
			} catch (PharException $exception) {
				throw new Exception('Error adding executable to Phar archive');
			}
			if (!$phar->setStub('#!' . exec('which env') . " php\n<?php Phar::mapPhar('$basename.phar');require'phar://$basename.phar/$basename';__HALT_COMPILER();")) {
				throw new Exception('Error setting executable Phar stub');
			} else {

				// Close the archive
				try {
					$phar->stopBuffering();
				} catch (PharException $exception) {
					throw new Exception('Error writing Phar archive to disk');
				}

				// Attempt to remove the .phar suffix and set executable permissions (non-critical if either of these fail)
				if (@rename("$buildDirectory/$basename.phar", "$buildDirectory/$basename")) {
					@chmod("$buildDirectory/$basename", 0755);
				} else {
					@chmod("$buildDirectory/$basename.phar", 0755);
				}
			}
		}
	}
}
