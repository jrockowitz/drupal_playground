#!/bin/bash

# Shared library of helper code for DDEV commands.
# @see .ddev/commands/host
# @see .ddev/commands/web

# Split options and parameters into dedicated variables.
OPTIONS=""
PARAMETERS=""
for argument in $@;
do
  if  [[ $argument == -* ]] ;
  then
    OPTIONS="$OPTIONS $argument"
  else
    PARAMETERS="$PARAMETERS $argument"
  fi
done
OPTIONS="${OPTIONS#"${OPTIONS%%[![:space:]]*}"}"
PARAMETERS="${PARAMETERS#"${PARAMETERS%%[![:space:]]*}"}"

# Define default path to sandbox, if it exists, or custom.
if [ -d "$DDEV_DOCROOT/modules/sandbox" ]; then
  PARAMETERS=${PARAMETERS:-$DDEV_DOCROOT/modules/sandbox}
else
  PARAMETERS=${PARAMETERS:-$DDEV_DOCROOT/modules/custom}
fi

# Convert absolute path on the host to relative path on the guest.
PARAMETERS=$(echo $PARAMETERS | sed -r "s|[^ ]+/$DDEV_DOCROOT/modules/|$DDEV_DOCROOT/modules/|g")
PARAMETERS=$(echo $PARAMETERS | sed -r "s|[^ ]+/$DDEV_DOCROOT/recipes/|$DDEV_DOCROOT/recipes/|g")

# Echo via color.
#
# Usage:
# _echo $BOLD_BLUE '--------------------------------------------------'
# _echo $BOLD_BLUE "Running $command..."
# _echo $BOLD_BLUE '--------------------------------------------------'
function _echo() {
  echo -e "$1""${@:2}"$NORM$RESET
}

BLACK='\033[0;30m'
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
PINK='\033[0;35m'
CYAN='\033[0;36m'
WHITE='\033[0;37m'

BOLD=$(tput bold)
BOLD_BLACK='\033[1;30m'
BOLD_RED='\033[1;31m'
BOLD_GREEN='\033[1;32m'
BOLD_YELLOW='\033[1;33m'
BOLD_BLUE='\033[1;34m'
BOLD_PINK='\033[1;35m'
BOLD_CYAN='\033[1;36m'
BOLD_WHITE='\033[1;37m'

RESET='\033[0m'
NORM=$(tput sgr0)
