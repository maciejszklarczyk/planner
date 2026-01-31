# Frontend Deployment Guide

## Overview
This document describes the deployment process for the Next.js frontend to production at https://planner.msolve.it

## Architecture
- **Platform**: Next.js 16 with standalone output
- **Container**: Docker multi-stage build
- **Registry**: GitLab Container Registry
- **CI/CD**: GitLab CI with docker executor
- **Reverse Proxy**: Traefik (zbyszek-network)
- **Deployment**: Manual trigger via GitLab CI

## Prerequisites on Server

### 1. Create Deployment Directory
```bash
sudo mkdir -p /home/maciej/docker/apps/planner/frontend
sudo chmod 755 /home/maciej/docker/apps/planner/frontend
```

### 2. Create Production .env File
Create `/home/maciej/docker/apps/planner/frontend/.env`:
```env
# Project name (for container naming)
PROJECT_NAME=plan

# Node environment
NODE_ENV=production
PORT=3000
```

**Note**: `NEXT_PUBLIC_API_URL` is NOT needed here. It's set at Docker build time in GitLab CI (see `.gitlab-ci.yml` variables section). The production value `https://api-planner.msolve.it` is baked into the Docker image during the build stage.

### 3. Update GitLab Runner Config
Edit `/etc/gitlab-runner/config.toml` and add volume mount:
```toml
[[runners]]
  [runners.docker]
    volumes = [
      "/var/run/docker.sock:/var/run/docker.sock",
      "/home/maciej/docker/apps/planner/frontend:/home/maciej/docker/apps/planner/frontend"
    ]
```

Restart runner:
```bash
sudo systemctl restart gitlab-runner
sudo gitlab-runner verify
```

## Deployment Process

### Automatic Build (on push to main)
1. Push changes to `main` branch
2. GitLab CI automatically builds Docker image
3. Image is pushed to GitLab Container Registry with tags:
   - `latest`
   - `${CI_COMMIT_SHORT_SHA}`

### Manual Deployment
1. Go to GitLab → CI/CD → Pipelines
2. Find the pipeline for your commit
3. Click "Play" button on `deploy-production` stage
4. Wait for deployment to complete (~30 seconds)

## Verification

### Check Container Status
```bash
ssh your-server
cd /home/maciej/docker/apps/planner/frontend
docker compose -f docker-compose.prod.yaml ps
```

Expected output:
```
NAME              IMAGE                                          STATUS
plan-frontend     registry.gitlab.com/.../frontend:latest        Up (healthy)
```

### Check Logs
```bash
docker compose -f docker-compose.prod.yaml logs -f frontend
```

Expected: "ready - started server on 0.0.0.0:3000"

### Test Public Access
```bash
curl -I https://planner.msolve.it
```

Expected: HTTP 200 OK

### Test in Browser
1. Navigate to https://planner.msolve.it
2. Should see login page
3. Login with credentials
4. Check DevTools → Application → Cookies:
   - `PLANNER_SESSION` should be present
   - Secure: ✓
   - HttpOnly: ✓
   - SameSite: Lax

## Rollback

If you need to rollback to a previous version:

1. Find the previous commit SHA in GitLab
2. SSH to server:
```bash
cd /home/maciej/docker/apps/planner/frontend
docker compose -f docker-compose.prod.yaml down
```

3. Edit docker-compose.prod.yaml temporarily:
```yaml
image: registry.gitlab.com/planner6551704/frontend:PREVIOUS_SHA
```

4. Pull and restart:
```bash
docker compose -f docker-compose.prod.yaml pull
docker compose -f docker-compose.prod.yaml up -d
```

## Troubleshooting

### Container won't start
```bash
# Check logs
docker compose -f docker-compose.prod.yaml logs frontend

# Common issues:
# - Missing .env file
# - Wrong NEXT_PUBLIC_API_URL
# - Port 3000 already in use
```

### Traefik not routing traffic
```bash
# Check if container is in zbyszek-network
docker network inspect zbyszek-network | grep plan-frontend

# Check Traefik labels
docker inspect plan-frontend | grep traefik

# Check Traefik logs
docker logs traefik | grep planner-frontend
```

### CORS errors
```bash
# Test CORS from server
curl -H "Origin: https://planner.msolve.it" \
     -H "Access-Control-Request-Method: POST" \
     -X OPTIONS https://api-planner.msolve.it/auth/login -v

# Should return:
# Access-Control-Allow-Origin: https://planner.msolve.it
# Access-Control-Allow-Credentials: true
```

### Session cookie not being set
1. Check backend configuration (should be `cookie_secure: auto`, `cookie_samesite: lax`)
2. Verify HTTPS is working (cookies with Secure flag only work over HTTPS)
3. Check browser DevTools → Network → Response Headers for `Set-Cookie`

## Files Structure

```
/home/maciej/docker/apps/planner/frontend/
├── .env                          # Production environment (NOT in git)
└── docker-compose.prod.yaml      # Copied from repo during deploy
```

## Security Notes

- Container runs as non-root user (nextjs:1001)
- Multi-stage build minimizes attack surface
- HTTPS enforced by Traefik
- Session cookies use Secure and HttpOnly flags
- CORS whitelist restricts API access
- Environment variables stored securely on server (not in git)

## Maintenance

### Update dependencies
1. Update `package.json` locally
2. Test locally: `npm install && npm run build`
3. Commit and push to `main`
4. GitLab CI builds new image
5. Manually trigger deployment

### View resource usage
```bash
docker stats plan-frontend
```

### Cleanup old images
```bash
docker image prune -a
```
