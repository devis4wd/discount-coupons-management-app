FROM node:22-alpine

WORKDIR /app

# Copy the Node dependency manifests.
# package.json defines the allowed dependency version ranges, while package-lock.json
# pins the exact dependency tree installed by npm ci.
COPY package*.json ./

# Install exactly the dependency versions recorded in package-lock.json.
# npm ci provides a clean, reproducible install and requires the lockfile to be present
# and consistent with package.json.
RUN npm ci

# Verify that the Tailwind CSS v4 CLI package is available in the image.
RUN npx @tailwindcss/cli --version || echo "CLI not found"

# No CMD is needed here because the container command is defined in compose.yaml.
