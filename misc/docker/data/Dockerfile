FROM ubuntu:14.04

# The tmp directory is not mounted from the host so that we can run Piwik is isolation
VOLUME /app/tmp

# We change the entrypoint to always run chmod when the container starts
# That's the only solution I found to have the /app/tmp volume with 777 permissions
ENTRYPOINT chmod -R 777 /app/tmp && /bin/bash
