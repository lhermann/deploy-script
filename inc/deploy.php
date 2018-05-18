<?php

require_once('Log.php');
require_once('Request.php');

/**
 * PHP Git deploy script by Lukas Hermann
 *
 * Automatically deploy the code using PHP, Git and rsync.
 *
 * Thanks to Marko Marković (https://github.com/markomarkovic/simple-php-git-deploy/)
 * and Miloslav Hůla (https://gist.github.com/milo/daed6e958ea534e4eba3)
 *
 * @version 1.1.0
 * @author  Lukas Hermann (https://github.com/lhermann)
 * @link    https://github.com/lhermann/
 */

set_error_handler(function($severity, $message, $file, $line) {
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function($e) {
    if(Request::is_active()) header('HTTP/1.1 500 Internal Server Error');
    Log::write(sprintf(
        "Error in %s on line %s: %s",
        basename($e->getFile()),
        $e->getLine(),
        htmlSpecialChars($e->getMessage())
    ));
    exit();
});


/**
 * Default constants
 */
Request::start();
$locale='en_US.UTF-8';
define('TIME_LIMIT', 60);
putenv('LC_ALL='.$locale);
putenv('PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:');
setlocale(LC_ALL,$locale);
define('BASEDIR', dirname(__DIR__));
define('PROJECTNAME', basename(FILENAME, '.config.php'));
define('LOCALREPOSITORY', BASEDIR . '/' . PROJECTNAME . '.repo/');


/**
 * Force SSL
 */
if( REQUIREHTTPS && !( $_SERVER['REQUEST_SCHEME'] == 'https' || $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' ) ) {
    throw new \Exception("Insecure Connection. Please connect via HTTPS.");
}


/**
 * This script will verify the secret (if set) and fetch the payload into `$github_payload`
 */
if(defined('DEBUG') && DEBUG) {
    $github_event = 'push';
    $github_payload = json_decode(
        file_get_contents(__DIR__ . '/example_payload.json')
    );
} else {
    require( 'github-webhook-handler.php' );
}


/**
 * Evaluate Request
 */
switch (strtolower($github_event)) {
    case 'ping':
        echo 'pong';
        exit();

    case 'push':
        // verify branch otherwise bail
        if(basename($github_payload->ref) !== BRANCH) {
            print("This script only deploys on push to '".BRANCH."' branch");
            exit();
        }
        break;

    default:
        // For debug only. Can be found in GitHub hook log.
        header('HTTP/1.0 404 Not Found');
        echo "Event:\n$github_event\n\nPayload:\n";
        print_r($github_payload);
        exit();
}


/**
 * Setup logfile
 */
Log::set_file(sprintf('%s_%s.log',
    PROJECTNAME,
    date('Y-m-d_His', strtotime($github_payload->head_commit->timestamp))
));
Log::setup_logfile();
Log::write("Event: $github_event");
Log::write("Head Commit:", false);
Log::write($github_payload->head_commit, false);
Log::write();


/**
 * Prepare Deploy
 * (Output is returned)
 */
prepare_deploy();


/**
 * Return http request but continue executing in order not to exceed
 * Github's 10s timeout on webhooks
 */
print("======[ Closing connection to avoid webhook timeout ]======\n");
print("Full log can be found at " . Log::filename() . "\n\n");
Log::disable_print();
Request::end();


/**
 * Deploy
 * (Output is not returned, only written to log)
 */
deploy();
Log::write();
Log::write("======[ Deployment finished ]======");
exit();







/*********************************************
 * HELPER FUNCTIONS
 *********************************************/

/**
 * Prepare the application deploy process
 */
function prepare_deploy() {

    // Determine required binaries
    $binaries = array('git', 'rsync');
    foreach( BUILDPIPELINE as $command ) {
        $binaries[] = explode(" ", $command)[0];
    }

    // Check Environment
    if( !checkEnvironment( $binaries )) {
        cleanup( LOCALREPOSITORY );
        return;
    }

    // Fetch updates from Git repository
    if( !gitPull( REMOTEREPOSITORY, LOCALREPOSITORY, BRANCH, LOCALREPOSITORY . VERSION_FILE )) {
        cleanup( LOCALREPOSITORY );
        return;
    }
}

/**
 * The application deploy process
 */
function deploy() {

    // Execute build pipeline
    if( !build( LOCALREPOSITORY, BUILDPIPELINE )) {
        cleanup( LOCALREPOSITORY );
        return;
    }

    // Copy files to production environment
    if(defined('SOURCEDIR') && defined('TARGETDIR')) {
        $sourcetarget = array([SOURCEDIR, TARGETDIR]);
    } else {
        $sourcetarget = SOURCETARGET;
    }
    if( !copyToProduction( LOCALREPOSITORY, $sourcetarget, DELETE_FILES, EXCLUDE )) {
        cleanup( LOCALREPOSITORY );
        return;
    }

    // Execute post deploy pipeline
    if( !post( LOCALREPOSITORY, POSTPIPELINE )) {
        cleanup( LOCALREPOSITORY );
        return;
    }

    // Remove the `LOCALREPOSITORY` (depends on CLEAN_UP)
    cleanup( LOCALREPOSITORY );
}


/**
 *
 */
function checkEnvironment($binaries = array()) {
    Log::write("======[ Checking the environment ]======");
    Log::write("Running as " . trim(shell_exec('whoami')));

    foreach ($binaries as $command) {
        $path = trim(shell_exec('which ' . $command));
        $shell_cmd = ['cd'];
        // return of 'which' is empty for shell commands like cd
        if ( $path == '' && !in_array($command, $shell_cmd) ) {
            // header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
            throw new \Exception(sprintf('%s not available. It needs to be installed on the server for this script to work.', $command));
        } else {
            $version = explode("\n", shell_exec($command.' --version'));
            Log::write(sprintf('%s : %s', $path, $version[0]));
        }
    }

    Log::write("Environment OK.");
    Log::write();
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
    Log::write("======[ Pulling from Repository ]======");
    return executeCommands($localrepo, $commands);
}


/**
 *
 */
function build($localrepo, $commands = array()) {
    if( !is_string($localrepo) || $localrepo === NULL ) throw new \Exception("Argument 1 of " . __FUNCTION__ . " must be a defined string.");

    // Execute commands
    Log::write("======[ Executing build pipeline ]======\n");
    return executeCommands($localrepo, $commands);
}


/**
 *
 */
function copyToProduction($localrepo, $sourcetarget, $delete_files = false, $excludearray = array()) {
    if( !is_string($localrepo) || $localrepo === NULL ) throw new \Exception("Argument 1 of " . __FUNCTION__ . " must be a defined string.");
    if( !is_array($sourcetarget) || $sourcetarget === NULL ) throw new \Exception("Argument 2 of " . __FUNCTION__ . " must be a defined array.");

    // Unserialize exclude parameters
    $exclude = '';
    foreach ($excludearray as $exc) {
        $exclude .= ' --exclude='.$exc;
    }

    // Copy command
    $commands = array();
    foreach ($sourcetarget as $value) {
        $commands[] = sprintf('rsync -azvO %s %s %s %s',
            $localrepo . $value[0],
            $value[1],
            ($delete_files) ? '--delete-after' : '',
            $exclude
        );
    }

    // Execute commands
    Log::write("======[ Copying files to production environment ]======\n");
    return executeCommands($localrepo, $commands);
}

/**
 *
 */
function post($localrepo, $commands = array()) {
    if( !is_string($localrepo) || $localrepo === NULL ) throw new \Exception("Argument 1 of " . __FUNCTION__ . " must be a defined string.");

    // Execute commands
    Log::write("======[ Executing post pipeline ]======\n");
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
        Log::write("======[ Cleaning up temporary files ]======\n");
        return executeCommands($localrepo, $commands);
    }
    return true;
}


/**
 *
 */
function executeCommands($local, $commands = array()) {
    if( !is_string($local) || $local === NULL ) throw new \Exception("Argument 1 of " . __FUNCTION__ . " must be a defined string.");

    foreach ($commands as $command) {
        set_time_limit(TIME_LIMIT); // Reset the time limit for each command
        if (file_exists($local) && is_dir($local)) {
            chdir($local); // Ensure that we're in the right directory
        }
        $tmp = array();
        $return_code = "";
        exec($command.' 2>&1', $output, $return_code); // Execute the command

        // Output the result
        Log::write('$ ' . $command);
        foreach ($output as $line) {
            Log::write($line);
        }
        Log::write();

        // Error handling and cleanup
        if ($return_code !== 0) {
            Log::write("Error encountered!");
            Log::write("Stopping the script to prevent possible data loss.");
            Log::write("CHECK THE DATA IN YOUR TARGET DIR!");
            Log::write("Stopping script execution");

            // Log the error
            $error = sprintf('Deployment error on %s using %s!',
                $_SERVER['HTTP_HOST'],
                __FILE__
            );
            error_log($error);
            return false;
        }
    }
    Log::write("(" . basename(__FILE__) . ") Done.");
    Log::write();
    return true;
}

?>
