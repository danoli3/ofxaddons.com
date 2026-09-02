source "https://rubygems.org"
ruby "2.4.4"

gem "rails", "~>4.2.10"
gem "bigdecimal", "~>1.3.0"                                         # pin to the version bundled with ruby 2.4.4 -
                                                                     # unpinned, bundler resolves the newest release,
                                                                     # which requires ruby >= 2.6
gem "font-awesome-sass"                                             # font icons
gem "high_voltage", "~>3.0"                                         # serving static pages (wrapped in the layout)
gem "lograge", "~>0.4"                                              # denser application logs
gem 'omniauth-github',                                              # github login
    git: 'https://github.com/omniauth/omniauth-github',
    tag: 'v1.4.0'
gem "mysql2", "~>0.5"                                               # database driver (DreamHost shared has no Postgres)
gem "redis-rails", "~>5.0"                                          # cache store
gem "dotenv-rails", "~>2.1.1"                                       # loads environment from .env file in every env (DreamHost
                                                                     # shared has no /etc/environment access for setting these
                                                                     # system-wide the way production normally does)
gem "simple_form", "~>3.3"                                          # form builder
gem "slim", "~>3.0"                                                 # HTML template language
gem "whenever", "~>0.9"                                             # cron job support

group :assets, :development, :test do
  gem "autoprefixer-rails", "~>6.5.0"                               # automatic vendor-specific CSS prefixing
  gem "bootstrap-sass", "~>3.4.0"                                   # SASS port of Bootstrap CSS framework
  gem "coffee-rails", "~>4.2.0"                                     # coffeescript asset pipeline integration
  gem "jquery-rails", "~>4.2.0"                                     # jQuery integration for rails
  gem "sassc-rails", "~>2.1"                                        # SASS support for rails
  gem "uglifier", ">= 1.3.0"                                        # javascript compressor
end

group :bin do
  gem "awesome_print"                                               # pretty print ruby objects
  gem "colorize"                                                    # colorized console output
  gem "httparty", "~>0.14.0"                                        # http connection library
  gem "nokogiri", "~>1.10.0"                                        # used for scraping readme files
  gem "loofah", "~>2.19.0"                                          # transitive dep of rails-html-sanitizer - pinned because
                                                                     # unpinned it resolves to a version that hard-requires
                                                                     # Nokogiri::HTML4, an API nokogiri 1.10.x doesn't have
end

group :development, :test do
  gem "byebug"                                                      # debugger
  gem "capistrano", "~>3.10.0"                                      # deployment automation
  gem "capistrano-bundler"
  gem "capistrano-passenger"
  gem "capistrano-rails"
  gem "capistrano-rbenv"
  gem "immigrant", "~>0.3.5"                                        # detect foreign keys and generate migrations to create constraints
  gem "quiet_assets", "~>1.1.0"                                     # strip out all the asset serving noise from logs
  gem "spring", "~>2.0.0"                                           # rails preloader
  gem "web-console", '~>2.3'
  gem "yaml_db"                                                     # db data dump to YAML
end
