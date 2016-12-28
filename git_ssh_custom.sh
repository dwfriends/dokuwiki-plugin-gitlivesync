#!/bin/sh

ssh -o StrictHostKeyChecking=no -oBatchMode=yes -i "$GIT_SSH_KEY_FILE" -F "/dev/null" $*
