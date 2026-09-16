# DevOps roadmap

Noted on 2026-09-16. This is a future implementation plan; infrastructure and deployment settings have not been changed for this roadmap.

## Goal

Learn DevOps through this application while making releases tested, traceable, repeatable, and recoverable. Keep Coolify as the deployment platform and GitHub Actions as the automation runner. Start with staging and production isolation plus CI/CD; Kubernetes is not required for this phase.

## 1. Separate staging and production

- Use `develop` for staging and `main` for production.
- Create separate Coolify applications/environments and domains.
- Keep MySQL, Redis, upload volumes, application keys, and other secrets separate.
- Use sandbox payment credentials and test email delivery in staging.
- Give each environment a separate image reference; the current shared `playersaloons-app:latest` reference needs adjustment before both stacks run on the same Docker host.
- Both environments can initially use the same VPS if capacity permits; they still share the server's failure domain.

Learning focus: environment configuration, secrets, Docker Compose, networking, and persistent storage.

## 2. Automate checked deployments

- Extend the existing `.github/workflows/ci.yml` rather than creating a competing pipeline.
- Push to `develop`: run checks, then trigger staging deployment only after success.
- Test the change in staging, then merge a pull request into `main`.
- Merge to `main`: run checks, then trigger production deployment only after success.
- Protect `main` with required checks and pull requests.
- Use authenticated Coolify deployment webhooks with credentials stored in GitHub secrets.
- Disable direct push auto-deploy when GitHub Actions controls deployment, preventing deployment before checks finish.
- Prevent overlapping deployments to the same environment and verify deployment completion and health.
- Verify production startup behavior in CI, including that demo-only seeders are not invoked.

Migrations currently run in `docker/start.sh` before the web server starts. Avoid a pre-deployment command that requires the old application container to be running. Keep production demo-seeding restrictions in place.

Learning focus: CI/CD, branch protections, release automation, and startup validation.

## 3. Build and promote versioned images

- Build a Docker image in GitHub Actions and publish it to a container registry.
- Tag releases by commit SHA and retain the exact image digest.
- Deploy to staging, then promote the same tested image to production.
- Update the Compose services so web, worker, scheduler, and Reverb use the selected environment's release image.
- Account for frontend configuration embedded during asset builds before promoting one image across environments.

Learning focus: registries, reproducible builds, immutable releases, and image promotion.

## 4. Monitor and alert

- Monitor availability, application errors, disk usage, queue workers, scheduler, and WebSockets.
- Configure deployment failure and service failure alerts.
- Keep useful runtime logs available for diagnosis.

Learning focus: observability and incident response.

## 5. Back up and practice recovery

- Automate database and upload backups with retention and a copy outside the deployment server.
- Practice restoring backups into an isolated environment.
- Document how to select a previous image and recover a failed release.
- Design migrations for compatibility with releases where possible; reverting an image does not revert database changes.

Learning focus: backup verification, rollback, and disaster recovery.

## 6. Make infrastructure reproducible

After the deployment setup is stable, evaluate Ansible for server configuration and Terraform for supported infrastructure provisioning.

Learning focus: infrastructure as code and reproducible server setup.

## First implementation scope

Implement phases 1 and 2 first. Follow with versioned images, monitoring, and tested recovery. This note does not authorize provisioning paid infrastructure or changing live service settings.
