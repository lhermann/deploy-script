# Deploy Script
PHP deploy script via github webhooks

# Install
Clone the repo into a directory on your server. Copy ```example.config.php``` and edit configs as needed. Target ```your.config.php``` with your github webhook.

# Tips for the build pipeline
- To use ```bunde install``` as a local user, use ```bundle install --path ~/bundle```

# When using private repositories with multiple ssh keys
SSH will look for the user's ~/.ssh/config file. I have mine setup as:

    Host gitserv
        Hostname        github.com
        IdentityFile    ~/.ssh/id_rsa.github
        IdentitiesOnly  yes

And I add a remote git repository:

    git remote add origin git@gitserv:myrepo.git

# Thanks
- Deploy script is loosly based on Marko Marković's [simple-php-git-deploy](https://github.com/markomarkovic/simple-php-git-deploy/)
- Uses Miloslav Hůla's [github-webhook-handler.php](https://gist.github.com/milo/daed6e958ea534e4eba3)
