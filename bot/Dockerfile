FROM node:22-alpine

# Set working directory
WORKDIR /app

# Copy package info and install node modules
# This helps with caching the modules layer in docker
COPY package*.json ./
RUN npm install

# Install dependencies for canvas/qrcode if needed
RUN apk add --no-cache libc6-compat

# Copy bot files
COPY . .

# Expose port
EXPOSE 3000

# Start Bot! 
CMD ["node", "bot.js"]
