source "https://rubygems.org"
ruby "2.7.8"                                                        # bumped from 2.4.4 for the DreamHost test deploy - fixes
                                                                     # an ffi/libffi ABI incompatibility that broke sassc on
                                                                     # the box's modern system libffi. Rails 4.2 is the ceiling
                                                                     # on how far this can go: Ruby 3.0 changed keyword-argument
                                                                     # handling in ways Rails 4.2 doesn't tolerate.

gem "rails", "~>4.2.10"
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
  gem "ffi", "~>1.16.0"                                             # transitive dep of sassc, via FFI bindings to libsass -
                                                                     # pinned to the last release line before ffi started
                                                                     # shipping precompiled linux binaries (which require ruby
                                                                     # >= 3.0, conflicting with the 2.7.8 pin above); 1.16.x is
                                                                     # source-only on linux and fixes the libffi 3.4 ABI issue
                                                                     # that 1.15.5 had
  gem "uglifier", ">= 1.3.0"                                        # javascript compressor
end

group :bin do
  gem "awesome_print"                                               # pretty print ruby objects
  gem "colorize"                                                    # colorized console output
  gem "httparty", "~>0.14.0"                                        # http connection library
  gem "nokogiri"                                                    # used for scraping readme files - no longer pinned to
                                                                     # ~>1.10.0 now that ruby is bumped past the ceiling that
                                                                     # pin (and the matching loofah pin) existed for
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
