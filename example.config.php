<?php
/**
 * Deployment configuration
 *
 * @version 1.1.0
 */

/**
 * General Settings
 *
 * PROJECTNAME  the project's name
 * @var string
 */
define('PROJECTNAME', 'example.com');


/**
 * Pyload Settings
 *
 * REQUIREHTTPS whether to accept insequre connections or not
 * @var boolean
 *
 * SECRET       github's webhook secret
 * @var string
 */
define('REQUIREHTTPS', true);
define('SECRET', '123456789');


/**
 * Remote Repository
 *
 * REMOTEREPOSITORY the remote github repository
 * @var string
 *
 * BRANCH       The branch that's being deployed.
 *              Must be present in the remote repository.
 * @var string
 */
define('REMOTEREPOSITORY', 'https://lhermann@github.com/lhermann/example.git');
define('BRANCH', 'master');


/**
 * Local Repository
 *
 * TEMPDIR      the directory in which the local repository will be created.
 *              Local Repo: path/to/tempdir/PROJECTNAME.repo/
 * @var string
 *
 * VERSION_FILE a file containing the currently deployed version number, leave empty if not desired.
 * @var string
 *
 * CLEAN_UP     whether to keep the local repository or to delete it after deploy.
 *              It is recommended to keep the repository in order to save time for consecutive deploys.
 * @var boolean
 */
define('TEMPDIR', ''); // for the local repo
define('VERSION_FILE', 'VERSION');
define('CLEAN_UP', false);


/**
 * Build Pipeline
 *
 * BUILDPIPELINE commands to be executed in order to build the production version
 * @var serialized array of strings
 */
define('BUILDPIPELINE', array( // serialized array of strings
    'bundle install --path ~/deploy/bundle',
    'npm install',
    'bundle exec jekyll build'
));


/**
 * Paths for file transfer
 *
 * SOURCEDIR    is relative to the local repository. To copy the repository itself simply
 *              leave empty.
 * @var string
 *
 * TARGETDIR    is the production docroot
 * @var string
 *
 * DELETE_FILES Whether to delete the files that are not in the repository but are on the
 *              local (server) machine.
 *              !!! WARNING !!! This can lead to a serious loss of data if you're not
 *              careful. All files that are not in the repository are going to be deleted,
 *              except the ones defined in EXCLUDE section. BE CAREFUL!
 * @var boolean
 *
 * EXCLUDE      The directories and files that are to be excluded when updating the code.
 *              Normally, these are the directories containing files that are not part of
 *              code base, for example user uploads or server-specific configuration files.
 *              Use rsync exclude pattern syntax for each element.
 * @var serialized array of strings
 */
define('SOURCEDIR', 'build');
define('TARGETDIR', '~/example.com/');
define('DELETE_FILES', false);
define('EXCLUDE', serialize(array(
    '.git',
    '.gitignore'
)));


/**
 * Deploy Script
 *
 * DEPLOY_SCRIPT  relative path to deploy.php
 * @var string
 */
define('DEPLOY_SCRIPT', 'inc/deploy.php');

/**
 * Thank you, that's it
 */

if (file_exists(DEPLOY_SCRIPT)) {
    require_once DEPLOY_SCRIPT;
} else {
    header("HTTP/1.0 404 Not Found", true, 404);
    print("Deploy script no found!")
    exit();
}
?>


