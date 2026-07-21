## Setup & Development

This project uses [Laravel Sail](https://laravel.com/docs/sail), a Docker-based local development environment. Docker must be installed and running on your machine before you can use Sail.

### 1. Install and configure Docker

- Install [Docker Desktop](https://www.docker.com/products/docker-desktop/) (macOS/Windows) or Docker Engine (Linux), and make sure the Docker daemon is running.

- On **Ubuntu / Debian**, install Docker Engine from the official repository, then add your user to the `docker` group:

  ```bash
  sudo apt update
  sudo apt install -y ca-certificates curl
  sudo install -m 0755 -d /etc/apt/keyrings
  sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
  sudo chmod a+r /etc/apt/keyrings/docker.asc

  echo \
    "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu \
    $(. /etc/os-release && echo "${UBUNTU_CODENAME:-$VERSION_CODENAME}") stable" | \
    sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

  sudo apt update
  sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

  sudo systemctl enable --now docker
  sudo usermod -aG docker $USER
  newgrp docker
  ```

- On **openSUSE**, install the Docker package, enable the service, then add your user to the `docker` group (the group is created automatically by the package):

  ```bash
  sudo zypper install docker          # or: docker-stable
  ```
  ```bash
  sudo systemctl enable --now docker
  ```
  ```bash
  sudo usermod -aG docker $USER
  ```
  ```bash
  newgrp docker
  ```

  Alternatively, openSUSE offers Podman as a Docker-compatible runtime — install `podman` and `podman-docker` (which provides a `docker` shim) instead.

- Verify the installation with:

  ```bash
  docker --version
  ```
  ```bash
  docker ps
  ```

### 2. Set up the `sail` alias

Laravel Sail is run through the local `vendor/bin/sail` binary. Typing that path every time is tedious, so set a shell alias. For Bash/Zsh, add this to your `~/.bashrc` or `~/.zshrc`:

```bash
alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'
```

This alias runs `sail` from the project root if a `sail` script exists, otherwise falls back to `vendor/bin/sail`. Reload your shell afterwards:

```bash
source ~/.bashrc   # or: source ~/.zshrc
```

### 3. Install PHP dependencies

From the project root (`tikatest-backend`), install Composer dependencies. If you have PHP/Composer locally you can run `composer install` directly; otherwise use the Composer Docker image:

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs
```

### 4. Configure environment

Copy the example environment file and generate the application key:

```bash
cp .env.example .env
sail artisan key:generate
```

### 5. Build and run the services

Start the Sail containers (this builds the images on first run):

```bash
sail up -d
```

Run database migrations and seed the database:

```bash
sail artisan migrate --seed
```

The application will be available at [http://localhost](http://localhost).

### Useful Sail commands

```bash
sail up -d            # start containers in the background
sail down             # stop containers
sail restart          # restart containers
sail artisan <cmd>    # run any Artisan command
sail composer <cmd>   # run Composer commands
sail npm <cmd>        # run npm commands
sail shell            # open a bash shell inside the application container
sail mysql            # open a MySQL shell
```

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
