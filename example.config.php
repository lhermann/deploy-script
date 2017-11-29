<?php
/****************************
 * Deployment configuration *
 ****************************/

/**
 * Remote Repository
 *
 * REMOTEREPOSITORY the remote github repository
 * BRANCH       The branch that's being deployed.
 *              Must be present in the remote repository.
 * SECRET       github's webhook secret
 * REQUIREHTTPS whether to accept insequre connections or not
 * CLEAN_UP      whether to keep the local repository or to delete it after deploy.
 *              It is recommended to keep the repository in order to save time for consecutive deploys.
 * VERSION_FILE  a file containing the currently deployed version number, leave empty if not desired.
 */
define('REMOTEREPOSITORY',  'https://lhermann@github.com/lhermann/example.git');
define('BRANCH',            'master');
define('SECRET',            '123456789');
define('REQUIREHTTPS',       true);
define('CLEAN_UP',           false);
define('VERSION_FILE',      'VERSION');


/**
 * Build
 *
 * BUILDPIPELINE commands to be executed in order to build the production version
 */
define('BUILDPIPELINE', array( // array of strings
    'npm install',
    'npm run build'
));


/**
 * Deploy
 * DELETE_FILES  Whether to delete the files that are not in the repository but are on the
 *              local (server) machine.
 *              !!! WARNING !!! This can lead to a serious loss of data if you're not
 *              careful. All files that are not in the repository are going to be deleted,
 *              except the ones defined in EXCLUDE section. BE CAREFUL!
 * SOURCETARGET Array with one or more from-to pairs for rsync
 * EXCLUDE      The directories and files that are to be excluded when updating the code.
 *              Use rsync exclude pattern syntax for each element.
 */
define('DELETE_FILES', false);
define('SOURCETARGET', array(
    ['dist', '$HOME/example.com/']
));
define('EXCLUDE', array(
    '.git',
    '.gitignore'
));


/**
 * Post Deploy
 *
 * POSTPIPELINE commands to be executed after the deployment
 */
define('POSTPIPELINE', array( // array of strings
    'npm run deploy'
));


/**
 * Thank you, that's it
 */
define('FILENAME',       __FILE__);
define('BASE_DIR',       __DIR__);
define('DEPLOY_SCRIPT', 'inc/deploy.php');
if (file_exists(DEPLOY_SCRIPT)) {
    require_once DEPLOY_SCRIPT;
} else {
    header("HTTP/1.0 404 Not Found", true, 404);
    print("Deploy script no found!");
    exit();
}
?>
