<?php
/**
 * PHP Git deploy script by Lukas Hermann
 *
 * Automatically deploy the code using PHP, Git and rsync.
 *
 * Thanks to Marko Marković (https://github.com/markomarkovic/simple-php-git-deploy/)
 * and Miloslav Hůla (https://gist.github.com/milo/daed6e958ea534e4eba3)
 *
 * @version 1.1.0
 * @author 	Lukas Hermann (https://github.com/lhermann)
 * @link    https://github.com/lhermann/
 */

set_error_handler(function($severity, $message, $file, $line) {
	throw new \ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function($e) {
	header('HTTP/1.1 500 Internal Server Error');
	echo "Error in " . basename($e->getFile()) . " on line {$e->getLine()}: " . htmlSpecialChars($e->getMessage());
	die();
});

/**
 * Default constants
 */
define('TIME_LIMIT', 30);
putenv('PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:');


if( REQUIREHTTPS && !( $_SERVER['REQUEST_SCHEME'] == 'https' || $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' ) ) {
	throw new \Exception("Insecure Connection. Please connect via HTTPS.");
}

/**
 * This script will verify the secret (if set) and fetch the payload into `$payload`
 */
require( 'github-webhook-handler.php' );

switch (strtolower($_SERVER['HTTP_X_GITHUB_EVENT'])) {
	case 'ping':
		echo 'pong';
		break;

	case 'push':
		deploy();
		break;

//	case 'create':
//		break;

	default:
		header('HTTP/1.0 404 Not Found');
		echo "Event:$_SERVER[HTTP_X_GITHUB_EVENT] Payload:\n";
		print_r($payload); # For debug only. Can be found in GitHub hook log.
		die();
}


/**
 * The application deploy process
 */
function deploy() {
	$remoterepo		= REMOTEREPOSITORY;
	$localrepo		= TEMPDIR . PROJECTNAME . '.repo/';
	$versionfile	= $localrepo . VERSION_FILE;
	$buildpipeline	= BUILDPIPELINE;

	// Determine required binaries
	$binaries = array('git', 'rsync');
	foreach( $buildpipeline as $command ) {
		$binaries[] = explode(" ", $command)[0];
	}

	// Check Environment
	if( !checkEnvironment( $binaries )) {
		cleanup( $localrepo );
		return;
	}

	// Fetch updates from Git repository
	if( !gitPull( $remoterepo, $localrepo, BRANCH, $versionfile )) {
		cleanup( $localrepo );
		return;
	}

	// Execute build pipeline
	if( !build( $localrepo, $buildpipeline )) {
		cleanup( $localrepo );
		return;
	}

	// Copy files to production environment
	if( !copyToProduction( $localrepo, SOURCEDIR, TARGETDIR, DELETE_FILES, EXCLUDE )) {
		cleanup( $localrepo );
		return;
	}

	// Remove the `$localrepo` (depends on CLEAN_UP)
	cleanup( $localrepo );
}


/**
 *
 */
function checkEnvironment($binaries = array()) {
	print("======[ Checking the environment ]======\n");
	print("Running as " . trim(shell_exec('whoami')) . "\n");

	foreach ($binaries as $command) {
		$path = trim(shell_exec('which ' . $command));
		if ($path == '') {
			header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
			throw new \Exception(sprintf('%s not available. It needs to be installed on the server for this script to work.', $command));
		} else {
			$version = explode("\n", shell_exec($command.' --version'));
			printf('%s : %s'."\n", $path, $version[0]);
		}
	}

	print("Environment OK.\n\n");
	return true;
}


/**
 *
 */
function gitPull($remote, $localrepo, $branch = 'master', $versionfile = NULL) {
	if( !is_string($remote) || $remote === NULL ) throw new \Exception("Argument 1 of " . __FUNCTION__ . " must be a defined string.");
	if( !is_string($localrepo) || $localrepo === NULL ) throw new \Exception("Argument 2 of " . __FUNCTION__ . " must be a defined string.");

	$commands = array();
	if (!is_dir($localrepo)) {
		// Clone the repository into the $localrepo directory
		$commands[] = sprintf(
			'git clone --depth=1 --branch %s %s %s',
			$branch,
			$remote,
			$localrepo
		);
	} else {
		// $localrepo repo exists and hopefully already contains the correct remote origin
		// so we'll fetch the changes and reset the contents.
		$commands[] = sprintf(
			'git --git-dir="%s.git" --work-tree="%s" fetch --tags origin %s',
			$localrepo,
			$localrepo,
			$branch
		);
		$commands[] = sprintf(
			'git --git-dir="%s.git" --work-tree="%s" reset --hard FETCH_HEAD',
			$localrepo,
			$localrepo
		);
	}

	// Update the submodules
	$commands[] = sprintf(
		'git submodule update --init --recursive'
	);

	// Describe the deployed version
	if (isset($versionfile) && $versionfile !== '') {
		$commands[] = sprintf(
			'git --git-dir="%s.git" --work-tree="%s" describe --always > %s',
			$localrepo,
			$localrepo,
			$versionfile
		);
	}

	// Execute commands
	print("======[ Pulling from Repository ]======\n");
	return executeCommands($localrepo, $commands);
}


/**
 *
 */
function build($localrepo, $buildpipeline = array()) {
	if( !is_string($localrepo) || $localrepo === NULL ) throw new \Exception("Argument 1 of " . __FUNCTION__ . " must be a defined string.");

	$commands = $buildpipeline;

	// Execute commands
	print("======[ Executing build pipeline ]======\n");
	return executeCommands($localrepo, $commands);
}


/**
 *
 */
function copyToProduction($localrepo, $sourcedir, $targetdir, $delete_files = false, $serializedexclude = '') {
	if( !is_string($localrepo) || $localrepo === NULL ) throw new \Exception("Argument 1 of " . __FUNCTION__ . " must be a defined string.");
	if( !is_string($sourcedir) || $sourcedir === NULL ) throw new \Exception("Argument 2 of " . __FUNCTION__ . " must be a defined string.");
	if( !is_string($targetdir) || $targetdir === NULL ) throw new \Exception("Argument 3 of " . __FUNCTION__ . " must be a defined string.");

	// Unserialize exclude parameters
	$exclude = '';
	foreach (unserialize($serializedexclude) as $exc) {
		$exclude .= ' --exclude='.$exc;
	}

	// Copy command
	$commands[] = sprintf('rsync -azvO %s %s %s %s',
		$localrepo . $sourcedir,
		$targetdir,
		($delete_files) ? '--delete-after' : '',
		$exclude
	);

	// Execute commands
	print("======[ Copying files to production environment ]======\n");
	return executeCommands($localrepo, $commands);
}


/**
 *
 */
function cleanup($localrepo) {
	if (CLEAN_UP) {
		$commands['cleanup'] = sprintf('rm -rf %s',
			$localrepo
		);

		// Execute commands
		print("======[ Cleaning up temporary files ]======\n");
		return executeCommands($localrepo, $commands);
	}
	return true;
}


/**
 *
 */
function executeCommands($local, $commands = array()) {
	if( !is_string($local) || $local === NULL ) throw new \Exception("Argument 1 of " . __FUNCTION__ . " must be a defined string.");

	$output = '';
	foreach ($commands as $command) {
		set_time_limit(TIME_LIMIT); // Reset the time limit for each command
		if (file_exists($local) && is_dir($local)) {
			chdir($local); // Ensure that we're in the right directory
		}
		$tmp = array();
		exec($command.' 2>&1', $tmp, $return_code); // Execute the command
		// Output the result
		printf("$ %s\n%s\n\n",
			trim($command),
			trim(implode("\n", $tmp))
		);
		$output .= ob_get_contents();
		ob_flush(); // Try to output everything as it happens

		// Error handling and cleanup
		if ($return_code !== 0) {
			header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
			printf("Error encountered!\nStopping the script to prevent possible data loss.\nCHECK THE DATA IN YOUR TARGET DIR!\nStopping script execution");

			// Log the error
			$error = sprintf('Deployment error on %s using %s!',
				$_SERVER['HTTP_HOST'],
				__FILE__
			);
			error_log($error);
			return false;
		}
	}
	print("(" . basename(__FILE__) . ") Done.\n\n");
	return true;
}

?>
