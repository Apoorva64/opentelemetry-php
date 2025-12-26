# Local Development with Xdebug - Setup Guide

This guide explains how to run the microservices locally (without Docker) with Xdebug debugging enabled in VSCode.

## Prerequisites

✅ **Already installed:**
- PHP 8.4
- Xdebug 3.5.0 
- Composer
- Required PHP extensions (sqlite3, curl, xml, zip, mbstring)

## Xdebug Configuration

Xdebug has been configured in `/etc/php/8.4/cli/conf.d/20-xdebug.ini` with:
```ini
xdebug.mode=debug
xdebug.start_with_request=yes
xdebug.client_port=9003
xdebug.client_host=localhost
xdebug.log=/tmp/xdebug.log
xdebug.log_level=7
```

## Quick Start

### 1. Setup All Services

Run the setup script to install dependencies and prepare databases:

```bash
./start-services.sh
```

### 2. Start Debugging in VSCode

1. Open the **Debug panel** (Ctrl+Shift+D or Cmd+Shift+D)
2. Select **"Listen for Xdebug"** from the dropdown
3. Click the **green play button** to start listening
4. Set **breakpoints** in your code by clicking to the left of line numbers

### 3. Start Services

You have two options:

#### Option A: Use VSCode Launch Configurations (Recommended)

In the Debug panel dropdown, select and run:
- **"Launch Menu Service"** - Starts on port 8000
- **"Launch Inventory Service"** - Starts on port 8002  
- **"Launch Billing Service"** - Starts on port 8003
- **"Launch Orders Service"** - Starts on port 8001

**Note:** You need to have "Listen for Xdebug" running first, then launch individual services.

#### Option B: Manual Terminal Launch

Open separate terminals for each service:

```bash
# Terminal 1 - Menu Service
cd services/menu
php -S localhost:8000 -t public

# Terminal 2 - Inventory Service  
cd services/inventory
php -S localhost:8002 -t public

# Terminal 3 - Billing Service
cd services/billing
php -S localhost:8003 -t public

# Terminal 4 - Orders Service
cd services/orders
php -S localhost:8001 -t public
```

## Service Endpoints

Once running, services are available at:

- **Menu Service**: http://localhost:8000
- **Orders Service**: http://localhost:8001  
- **Inventory Service**: http://localhost:8002
- **Billing Service**: http://localhost:8003

## Using Breakpoints

1. Open any PHP file in the services (e.g., [services/orders/src/Controller/OrderController.php](services/orders/src/Controller/OrderController.php))
2. Click to the left of a line number to set a breakpoint (red dot appears)
3. Make an API request to trigger that code path
4. Execution will pause at your breakpoint
5. Use the debug toolbar to:
   - **Continue** (F5)
   - **Step Over** (F10)
   - **Step Into** (F11)
   - **Step Out** (Shift+F11)
6. Inspect variables in the **Variables** panel
7. Evaluate expressions in the **Debug Console**

## Troubleshooting

### Breakpoints not working?

1. **Check Xdebug is loaded:**
   ```bash
   php -m | grep xdebug
   ```
   Should output: `xdebug`

2. **Check Xdebug log:**
   ```bash
   tail -f /tmp/xdebug.log
   ```

3. **Verify port 9003 is not in use:**
   ```bash
   lsof -i :9003
   ```

4. **Restart VSCode's debug listener:**
   - Stop the "Listen for Xdebug" configuration
   - Start it again
   - Refresh your browser/make a new request

### Service won't start?

1. **Check if port is already in use:**
   ```bash
   lsof -i :8000  # or 8001, 8002, 8003
   ```

2. **Kill existing process:**
   ```bash
   kill -9 <PID>
   ```

3. **Check composer dependencies are installed:**
   ```bash
   cd services/orders  # or menu, inventory, billing
   composer install
   ```

4. **Check database migrations:**
   ```bash
   php bin/console doctrine:migrations:migrate --no-interaction
   ```

### Still not working?

- Increase Xdebug log level in `/etc/php/8.4/cli/conf.d/20-xdebug.ini`
- Check VSCode's PHP Debug extension is installed
- Verify path mappings in [.vscode/launch.json](.vscode/launch.json) are correct

## Running Tests

With services running locally, you can run integration tests:

```bash
cd tests
./integration-test.sh
```

## Switching Back to Docker

To switch back to Docker-based development:

```bash
docker-compose up -d
```

Make sure to stop local PHP servers first to avoid port conflicts.

## Additional Resources

- [Xdebug Documentation](https://xdebug.org/docs/)
- [VSCode PHP Debugging](https://code.visualstudio.com/docs/languages/php#_debugging)
- [Symfony Development](https://symfony.com/doc/current/setup.html)
