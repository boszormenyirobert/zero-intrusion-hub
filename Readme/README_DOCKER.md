# Local project installation with Docker

## Prerequisites
0. Install the Mobile Application, but do not start it

1. WSL2
    Do not install anything manually. If you already have a distro, its availability may change because the next step creates and uses its own environment.
2. Docker Desktop
    Select Ubuntu.

## Infrastructure

Docker build files:

```text
infrastructure/docker/
```

Docker Compose files:

```text
infrastructure/docker/compose/
```

## Docker Compose Files

Main app stack:
   
```bash
docker compose -f infrastructure/docker/compose/compose.yml build app
docker compose -f infrastructure/docker/compose/compose.yml up -d --force-recreate app
```

## Monitoring and Redis Cache

```bash
docker compose -f infrastructure/docker/compose/compose.monitoring.yaml up -d
```

# Use alias. It is crucial to the smoothly network communication.
```
    hosts: (C:\Windows\System32\drivers\etc\hosts)
        127.0.0.0 hub-docker.local
```

## Handy App Tunnel

Run this in a shell to use the Handy App with the dockerized application:

```bash
ssh -N -T -R 0.0.0.0:8085:hub-docker.local:8092 root@${REMOTE_SERVER_IP}
```

Ports:

- 8085: Allows port forwarding on the remote server.
- 8092: The running hub Docker port.


## By running containers
- Open the hub-docker.local:8092
- Mobile Application Installation:
```
cd /c/ZeroIntrusion/android
adb install app/build/outputs/apk/release/app-release.apk
```

- Mobile Application Registration
- Start on hub-docker.local:8092 with the HUB instance registraion process
- Adjust Desktop App and Browser extension .env path: ${REMOTE_SERVER_IP}:8085