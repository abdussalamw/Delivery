FROM node:22-alpine

# Set working directory
WORKDIR /app

# Copy package info and install node modules
# This helps with caching the modules layer in docker
COPY package*.json ./
RUN npm install

# Copy bot files
COPY . .

# Expose port
EXPOSE 3000

# Start Bot! No need for PM2 inside Docker as docker acts as the process manager
CMD ["npm", "start"]
