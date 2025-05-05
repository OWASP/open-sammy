#!/bin/sh
GITHUB_REPO=https://github.com/devsecopsmaturitymodel/DevSecOps-MaturityModel-data.git
BRANCH=main

if [ ! -d "private/dsomm" ]; then
    mkdir -p private
    cd private
    echo 'Cloning DSOMM project...'
    git clone --single-branch -b $BRANCH $GITHUB_REPO dsomm
    cd ..
else
  cd private/dsomm
  echo 'Discarding local changes to DSOMM model (if any)...'
  git checkout $BRANCH
  git checkout -- .
  echo 'Pulling latest changes...'
  git pull
  cd ..
  cd ..
fi 