#!/bin/bash

VERSION=1-script
REPORTING_URL=https://techbox.stage.e115.com/cicd/upload

badFunction() {
    chmod -x abcde.txt # bad call, will cause _helpers.sh to exit with non-zero
    echo "haha"
    ls # good call
}

showDump() {
    echo "${yellow}# showDump${normal}"
    echo "${green}- Dumping all info for debugging${normal}"

    echo "${yellow}## ${green}Validating composer.json${normal}"
    if composer validate; then
        composer show --self --installed | grep avexsoft        
        echo "- [✅] composer.json is valid"
    else
        echo "- [❌] composer.json has errors"
    fi
    # composer why-not avexsoft/saas 10.0.54
    echo "${yellow}## ${green}Showing .git config${normal}"
    git config --list | cat

    echo "${yellow}## ${green}Showing contents of /env.json${normal}"
    echo "$(cat /env.json)"

    echo "${yellow}## ${green}Showing contents of github.json${normal}"
    echo "$(cat github.json)"

    # laravel project
    if [ -f storage/logs/laravel.log ]; then
        echo "${green}- Showing contents of storage/logs/laravel.log${normal}"
        cat storage/logs/laravel.log
    fi

    # laravel packages
    if [ -f vendor/orchestra/testbench-core/laravel/storage/logs/laravel.log ]; then
        echo "${yellow}## ${green}Showing contents of storage/logs/laravel.log${normal}"
        cat vendor/orchestra/testbench-core/laravel/storage/logs/laravel.log
    fi
}

# deprecated as too disruptive to commits
updateComposerJsonTimeIfDifferent() {
    return # deprecatedd as too disruptive to commits

    utcUnixCommit=$(git log -1 --format=%ct)
    utcDateCommit=$(date '+%Y-%m-%d %H:%M:%S' -u -d @$utcUnixCommit)
    utcDateComposer=$(jq -r '.time // empty' composer.json)
    utcUnixComposer=$(date +"%s" --date="${utcDateComposer}z")

    if [ "$utcUnixCommit" != "$utcUnixComposer" ]; then
        echo "Timestamping composer.json"

        # extract the commit message
        echo $GITHUB >github.json
        COMMIT_MSG=$(jq -r .event.head_commit.message <github.json)
        echo -n "[Timestamped] " >/recommit-message.txt
        echo $COMMIT_MSG >>/recommit-message.txt

        # update composer.json
        jq -c " .time=\"$utcDateCommit\" " composer.json >tmp.$$.json
        jq --indent 4 . tmp.$$.json >composer.json

        # prepare to commit + push
        echo https://token:${TOKEN}@www.avexcode.com >~/.git-credentials

        git config --unset http.https://www.avexcode.com/.extraheader
        git config user.name $(git log -1 --pretty="%an")
        git config user.email $(git log -1 --pretty="%ae")
        git config credential.helper store

        git config --list

        git add composer.json

        GIT_AUTHOR_DATE=$utcUnixCommit GIT_COMMITTER_DATE=$utcUnixCommit git commit -F /recommit-message.txt
        git push origin $ORIGIN_URL

        touch /need-update
        exit 1
    fi
}

setupTestEnvironment() {
    red=$(tput setaf 1)
    green=$(tput setaf 2)
    yellow=$(tput setaf 3)
    blue=$(tput setaf 4)
    purple=$(tput setaf 5)
    normal=$(tput setaf 7)

    # overwrite with the internal IP of our reporting hostnames
    # echo "192.168.214.10 satis.avexcode.com" >>/etc/hosts
    # echo "192.168.214.10 techbox.stage.e115.com" >>/etc/hosts
    # echo "192.168.214.10 techbox10.dv1.e115.com" >>/etc/hosts
    # echo "192.168.214.10 techbox10.dv3.e115.com" >>/etc/hosts

    # ping techbox10.dv1.e115.com
    # exit 1
    if [ ! -f /stats.json ]; then
        echo "{}" | cat >/stats.json
    fi

    echo Script version: ${VERSION}
    curl --version
    php -v

    echo "${green}Saving GITHUB into github.json${normal}"
    echo $GITHUB >github.json
    COMMIT_MSG=$(jq -r .event.head_commit.message <github.json)

    echo ${yellow}${EVENT}${normal} from ${yellow}${ACTOR}${normal}: ${yellow}$COMMIT_MSG${normal}
    echo "${green}- Branch is ${BRANCH} of ${REPO}"
    echo "${green}- Logs at ${URL}${REPO}/actions/runs/${RUN_ID}"

    echo "${green}- Saving ENV into temp1.json${normal}"
    jq -n '$ENV' >temp1.json

    # create env.json
    echo "${green}- Merging temp1 and github${normal}"
    jq '.GITHUB = [input]' temp1.json github.json >/env.json

    # add previous committer to env and github.json
    new_key='GITHUB_PREVIOUS_COMMITTER'
    previousCommitter=$(git log -1 --pretty="%an")
    jq --arg key "$new_key" --arg value "$previousCommitter" '. + {($key): $value}' github.json >tmp.json && mv tmp.json github.json
    jq --arg key "$new_key" --arg value "$previousCommitter" '. + {($key): $value}' /env.json >tmp.json && mv tmp.json /env.json

    /usr/sbin/mysqld --user=root &

    # block until mysqld is ready
    mysqladmin --silent --wait=30 ping || exit 1
    echo "${green}- MySQL is now running${normal}"

    # showDump # use only when trying to debug

    _preventModifiedMigrations
    _preventDevelopmentRepositories
}

_preventModifiedMigrations() {
    # "allow_modified_migrations": true,
    COMMIT_MSG=$(jq -r .event.head_commit.message <github.json)

    is_allowed=false
    if [[ $COMMIT_MSG == *"/modified_migrations"* ]]; then
        is_allowed=true
    fi

    echo "- Commit message ${yellow}$COMMIT_MSG${normal}"
    # is_allowed=$(jq -r .extra.laravel.allow_modified_migrations <composer.json)
    if [ $is_allowed == true ]; then
        echo "- ${yellow}/modified_migrations${normal} in commit message, will accept migrations that have been modified/deleted."
    else
        TAG_COUNT=$(git tag | wc -l)
        if [ $TAG_COUNT == 0 ]; then
            echo "- No tags founds, skipping migration check and revert"
            return
        fi

        LAST_TAG=$(git describe --abbrev=0 --tags)
        # check between last tag and latest commit for (M)odified or (D)eleted
        ! git diff --name-status $LAST_TAG HEAD | grep -q "^M\sdatabase/migrations/.*"
        e=$?
        fail=false
        if [ $e != 0 ]; then
            echo "- Detected modified migrations since $LAST_TAG"
            fail=true
        fi
        ! git diff --name-status $LAST_TAG HEAD | grep -q "^D\sdatabase/migrations/.*"
        e=$?
        if [ $e != 0 ]; then
            echo "- Detected deleted migrations since $LAST_TAG"
            fail=true
        fi

        if [ $fail == true ]; then
            echo "- Action will ${red}fail${normal}. To allow, add ${yellow}/modified_migrations${normal} to your commit message."
            exit 3
        fi
    fi
}

_preventDevelopmentRepositories() {
    output=$(jq "try(.repositories.MUST_ONLY_EXISTS_DURING_DEVELOPMENT)" composer.json)
    if [[ "$output" == "" || $output == null ]]; then
        exit 0
    else
        echo "- Development Repositories not allowed"
        exit 3
    fi
}

setupDependenciesWithComposer() {
    if [[ -f "artisan" ]]; then
        echo "${yellow}- Laravel project as artisan exists, creating .env and folders${normal}"

        # for packages
        echo "APP_ENV=testing" >.env

        # for old projects
        if [[ -f ".env.test" ]]; then
            cp .env.test .env
        fi

        # for new projects
        if [[ -f ".env.testing" ]]; then
            cp .env.testing .env
        fi

        mkdir -p storage/framework/sessions
        mkdir -p storage/framework/views
        mkdir -p storage/framework/cache
    else
        echo "${yellow}- Laravel package as artisan does not exist${normal}"
    fi

    # fix composer.json existence OR fix repositories
    if [ -f composer-prod.json ]; then
        # < v1.0.6 handling
        rm -f composer.json
        ln -s composer-prod.json composer.json
    else
        # v1.0.6 handling create production composer.json by modifying repositories
        echo "${yellow}- Pulling packages from only https://satis.avexcode.com${normal}"
        jq -c 'del(.repositories) + { "repositories": [
                  {
                      "type": "composer",
                      "canonical": false,
                      "url": "https://satis.avexcode.com",
                  }
              ]}' composer.json >tmp.$$.json
        rm composer.json
        mv tmp.$$.json composer.json
    fi

    # ensure these composer plugins can run
    composer config --no-plugins allow-plugins.composer/installers true
    composer config --no-plugins allow-plugins.pestphp/pest-plugin true 
    composer config --no-plugins allow-plugins.ergebnis/composer-normalize true 
    
    # unset COMPOSER_AUTH
    if [[ -f composer.lock ]]; then
        rm composer.lock
    fi

    composer config http-basic.satis.avexcode.com token ${TOKEN}
    composer update
    composer show
}

setupIfLaravelProject() {
    if [[ -f "artisan" ]]; then
        echo "${green}- Laravel project, ${yellow}running artisan${green} commands${normal}"

        # run these so that artisan commands can run properly
        php artisan optimize

        php artisan key:generate
        php artisan migrate:fresh
        php artisan migrate:roll
        php artisan migrate:fresh --seed

        # php artisan optimize:clear
        # php artisan schedule:clear-cache
        php artisan auth:clear-resets
        php artisan vendor:publish --tag laravel-assets --ansi --force

    else
        echo "${green}- Laravel package, ${yellow}not running artisan${green} commands${normal}"
    fi
}

runPhpUnitTests() {
    SECONDS=0 # SECONDS is a special bash variable that auto increments

    jq --arg key "FATAL_ERROR" '. + {($key): true}' /stats.json >tmp.json && mv tmp.json /stats.json
    jq --arg key "UNIT_TEST_DURATION" --arg value "$duration" '. + {($key): $value}' /stats.json >tmp.json && mv tmp.json /stats.json

    if [[ -f ".usepest" ]]; then
        echo "${green}Pest ${yellow}${normal}"
        # ./vendor/bin/pest --profile --testdox --log-events-text /phpunit-events.txt
        script -e -q -c "./vendor/bin/pest --profile --testdox --log-events-text /phpunit-events.txt" /slowest.txt
    else
        echo "${yellow}.usepest${green} not found, running PHPUnit${normal}"

        if [ ! -f ./vendor/bin/phpunit ]; then
            echo "${green}PHPUnit does not exists ${yellow}${normal}"
        else
            # this regex can catch "10.4-dev"
            export phpunit_full=$(./vendor/bin/phpunit --version | grep -Eo '[0-9]+\.[.0-9]+')
            export phpunit_major=$(echo $phpunit_full | cut -d. -f1)
            export IS_RUNNER=1

            echo "${green}PHPUnit ${yellow}${phpunit_major}${normal}"
            if ((phpunit_major > 9)); then
                # version >=10
                touch /phpunit-events.txt
                # ./vendor/bin/phpunit --testdox --log-events-verbose-text /phpunit-events.txt
                script -e -q -c "./vendor/bin/phpunit --testdox --log-events-verbose-text /phpunit-events.txt" /slowest.txt

                # > test ; cat test ; cat test | grep "│\|✘"
            else
                # version <=9
                touch /phpunit-testdox.xml
                # ./vendor/bin/phpunit --profile --testdox --testdox-xml /phpunit-testdox.xml
                script -e -q -c "./vendor/bin/phpunit --profile --testdox --testdox-xml /phpunit-testdox.xml" /slowest.txt
            fi
        fi
    fi

    jq --arg key "FATAL_ERROR" '. + {($key): false}' /stats.json >tmp.json && mv tmp.json /stats.json
    jq --arg key "UNIT_TEST_DURATION" --arg value "$SECONDS" '. + {($key): $value}' /stats.json >tmp.json && mv tmp.json /stats.json
}

revertIfAnyStepFailed() {
    showDump

    if # if an update is needed, don't revert
        [[ -f "/need-update" ]]
    then
        exit 0
    fi

    echo "${green}COMMITS is [$COMMITS]${normal}"
    if [ "$COMMITS" == "" ]; then
        echo "${green}So we are not reverting because this is most likely a dependency-test${normal}"
        exit 0
    fi

    TAG=$(git describe --abbrev=0 --tags)
    echo "${green}Reverting to last good tag [${yellow}$TAG${green}] and pushing${normal}"
    git reset --hard $TAG
    git push origin $URL --force
}

tagAndPublish() {
    git config credential.${URL}.helper "!f() { sleep 1; echo \"username=token\"; echo \"password=${TOKEN}\"; }; f"
    # next step will always have exit code of 0
    git config --unset http.${URL}.extraheader || true

    git fetch

    # tag with a version
    curl --fail-with-body --location -O https://token:${TOKEN}@www.avexcode.com/avexsoft/dev-tools/raw/devops/tag-from-commit.sh
    chmod +x tag-from-commit.sh
    ./tag-from-commit.sh
    git push origin ${REPO_URL} --tags

    echo "${green}Default branch is [${yellow}${DEFAULT_BRANCH}${green}]"
    echo "${green}Current branch is [${yellow}${REPO_URL}${green}]"

    # if default branch, then fast forward master
    if [ "${DEFAULT_BRANCH}" = "${REPO_URL}" ]; then
        echo "Fast forwarding"
        git checkout master --force
        git merge --ff-only ${REPO_URL}
        git push origin
    fi

    total_end=$(date +%s)
    jq --arg key "total_end" --arg value "$total_end" '. + {($key): $value}' /stats.json >tmp.json && mv tmp.json /stats.json
    # merge /stats.json into /env.json
    jq '.timings = (input)' /env.json /stats.json >tmp.json
    mv tmp.json /env.json

    # publish
    echo "${green}Publishing via REPORTING_URL: ${yellow}${REPORTING_URL}/publish${green}"
    curl \
        -F "action=publish" \
        -F "version=<tagged.version" \
        -F "json=</env.json" \
        --fail-with-body \
        ${REPORTING_URL}/publish
}

notifyWatchers() {
    # if an update is needed, don't notify
    if [[ -f "/need-update" ]]; then
        echo "Updating action YAML file, no need to inform watchers"
        clearFiles
        exit 0
    fi

    # merge /stats.json into /env.json as tagAndPublish does not always run
    jq '.timings = (input)' /env.json /stats.json >tmp.json
    mv tmp.json /env.json

    if [[ ! -f /phpunit-testdox.xml ]] && [[ ! -f /phpunit-events.txt ]]; then
        touch /phpunit-testdox.xml
    fi

    if [[ -f /phpunit-events.txt ]]; then
        ls -l /phpunit-events.txt
        echo "${green}Notifying via REPORTING_URL: ${yellow}${REPORTING_URL}/notify${green} of ${yellow}phpunit-events.txt${green} results${normal}"
        curl \
            -F "action=notify" \
            -F "json=</env.json" \
            -F "files[]=@/phpunit-events.txt" \
            --fail-with-body \
            ${REPORTING_URL}/notify
    else
        ls -l /phpunit-testdox.xml
        echo "${green}Notifying via REPORTING_URL: ${yellow}${REPORTING_URL}/notify${green} of ${yellow}phpunit-testdox.xml${green} results${normal}"
        curl \
            -F "action=notify" \
            -F "json=</env.json" \
            -F "files[]=@/phpunit-testdox.xml" \
            --fail-with-body \
            ${REPORTING_URL}/notify
    fi

    clearFiles
}

clearFiles() {
    if [ -f /env.json ]; then
        rm /env.json
    fi
    if [ -f tagged.version ]; then
        rm tagged.version
    fi
    if [ -f github.json ]; then
        rm github.json
    fi
}

red=$(tput setaf 1)
green=$(tput setaf 2)
yellow=$(tput setaf 3)
blue=$(tput setaf 4)
purple=$(tput setaf 5)
normal=$(tput sgr0)

# from https://unix.stackexchange.com/a/308314/566307,
# `set -e` will cause this script to break upon any errors (commands that return non-zero)
set -e
$1 "$@"
exit 0
