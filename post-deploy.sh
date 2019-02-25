
#! /bin/bash

while getopts "p:" opt; do
	case ${opt} in
		p )
			PROJECT_DIR=${OPTARG}
			;;
	esac
done

# Establish a symbolic link for the environment directory:
rm __environment
mkdir -p ../environment/${PROJECT_DIR}
ln -s ../environment/${PROJECT_DIR} __environment

# -/-/-/-/-
# Set up all the scheduled tasks
# -/-/-/-/-
# Set permissive permission
chmod 744 */scheduled-task*
chmod 744 setup/scheduled-tasks/*.{sh,php,js}

# Build a cumulative, consolidated crontab
CURRENT_WORKING_DIR=`pwd`
CRON_ENV="\n\nPATH=/bin:/usr/bin:/usr/local/bin:${CURRENT_WORKING_DIR}\nHOME=${CURRENT_WORKING_DIR}\n";
find -type f -name '*.crontab' -exec cat {} \; > tmp_crontab;
printf $CRON_ENV | cat - tmp_crontab | tee tmp_2_crontab;
rm tmp_crontab;
mkdir -p setup;
mv tmp_2_crontab setup/scheduled-tasks/all_tasks.crontab;
cp setup/scheduled-tasks/all_tasks.crontab __environment/scheduled-tasks/$PROJECT_DIR.crontab
cat __environment/scheduled-tasks/*.crontab | crontab -
