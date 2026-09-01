# ofxAddons

The central place to discover openFrameworks addons.

## Running app locally

### Prerequisites

1. Ruby 2.4.4
1. [Bundler](bundler.io) < 2.0
1. PostgreSQL 9.x (recommend using [homebrew](http://brew.sh/) or [mac ports](http://www.macports.org/) to install)

### Setup

1. Clone the repository:

        $ git clone https://github.com/atduskgreg/ofxaddons.com

1. Change directories in to the cloned repository:

        $ cd ofxaddons.com

1. Install the gems dependencies

        $ bundle install

1. Set up the database.

    You have two basic options: start with an empty database, or grab a backup.

    #### Start with an empty database

        $ rake db:setup

1. Create a dotenv file:

    WARNING: Never check in the `.env` file. It will screw up the production environment.

        $ touch .env

    Open up .env and add the following lines:

        PORT=5000
        WEB_CONCURRENCY=1

1. Launch the server:

        $ bundle exec unicorn

    You should now be able to navigate to load the web site at http://localhost:3000

### Crawling

#### API Keys
If you want to avoid rate limiting (hint: you _do_ — unauthenticated crawls are capped at 10 search requests/min and 60 API requests/hr, which isn't enough to complete a crawl) you need a Github [personal access token](https://github.com/settings/tokens) with no scopes (public read access is enough).

`GITHUB_TOKEN` is strictly optional. If you don't set it, the app will run fine, but you'll be subject to unauthenticated rate limiting, and the crawler will not be able to complete a full pass.

Once you've got your token, there are several ways to set up your environment, but here's one way using Foreman.

1. If needed, create a `.env` file in the repository root

        $ touch .env

   WARNING: Never check in the `.env` file. It will screw up the production environment.

1. Add your token to the file:

        GITHUB_TOKEN=ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx

1. Restart Foreman

Separately, `GITHUB_CLIENT_ID`/`GITHUB_CLIENT_SECRET` are used only for admin login via Github OAuth (`/auth/github`) — register an [OAuth App](https://github.com/settings/applications/new) for those, not a personal access token. They are unrelated to crawler rate limiting; Github retired authenticating API requests via `client_id`/`client_secret` query params in 2020.

Further reading on [using foreman for config vars](https://devcenter.heroku.com/articles/config-vars#using-foreman).

#### Running a Crawl

Crawling and updating is run through the script runner:

    $ rails r 'Importer.new.run'

The importer currently logs into the rails log for whatever env you're running (e.g. log/devlopment.log). A simple way to monitor is to tail the log file:

    $ tail -f log/development.log

##### Caching

By default the importer uses caching in the development environment. This helps speed up development when you're working on the importer since you skip all the HTTP request overhead and just read the responses off of the local disk. You can blow away the caches with `rake tmp:cache:clear`. Or you can manually delete individual caches files in `tmp/caches/importer`.

You can force caching behaiour by passing an options hash to run:

    $ rails r 'Importer.new.run(cache: false)'

If you pass `cache: true`, the importer will use cached responses from the github API (if available).
