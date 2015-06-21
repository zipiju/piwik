#!/usr/bin/env bash
if [ "$TEST_SUITE" = "UITests" ];
then
    # See https://github.com/travis-ci/travis-ci/issues/3225
    mkdir travis-phantomjs > /dev/null
    wget https://s3.amazonaws.com/travis-phantomjs/phantomjs-2.0.0-ubuntu-12.04.tar.bz2 -O $PWD/travis-phantomjs/phantomjs-2.0.0-ubuntu-12.04.tar.bz2 > /dev/null
    tar -xvf $PWD/travis-phantomjs/phantomjs-2.0.0-ubuntu-12.04.tar.bz2 -C $PWD/travis-phantomjs > /dev/null
fi
