# Use this file to easily define all of your cron jobs.
#
# It's helpful, but not entirely necessary to understand cron before proceeding.
# http://en.wikipedia.org/wiki/Cron

# Example:
#
# set :output, "/path/to/my/cron_log.log"
#
# every 2.hours do
#   command "/usr/bin/some_great_command"
#   runner "MyModel.some_method"
#   rake "some:great:rake:task"
# end
#
# every 4.days do
#   runner "AnotherModel.prune_old_records"
# end

# Learn more: http://github.com/javan/whenever


set :output, "/var/log/ofxaddons_importer.log"

job_type :rbenv_runner, %Q{export PATH=/opt/rbenv/shims:/opt/rbenv/bin:/usr/bin:$PATH; eval "$(rbenv init -)"; cd :path && bundle exec rails runner -e :environment ":task" --silent :output }

every 3.months do
  rake "tmp:clear"
end

# The crawl used to run here every hour, but with no locking that meant a
# crawl taking longer than an hour (which unauthenticated Github API rate
# limits guaranteed) got restarted mid-run by the next tick, clearing its
# cache and re-searching from scratch every time. See lib/importer.rb for
# the new file-lock guard. The crawl itself has moved to a scheduled
# Github Actions workflow (.github/workflows/crawl.yml), which SSHes in
# and runs it, so it isn't duplicated here.

# every 4.days do
#   runner "AnotherModel.prune_old_records"
# end
