compose=docker compose

dc:
	@${compose} -f docker-compose.yml $(cmd)

dcr:
	@make dc cmd="run --rm php-cli $(cmd)"

stop:
	@make dc cmd="stop"

up:
	@make dc cmd="up -d"

build-containers:
	@make dc cmd="up -d --build"

down:
	@make dc cmd="down"

console:
	@make dcr cmd="bin/console $(arg)"

migrate-diff:
	@make console arg="doctrine:migrations:diff"

migrate-run:
	@make console arg="doctrine:migrations:migrate"

phpunit:
	@make dcr cmd="vendor/bin/phpunit -d --enable-pretty-print -d --compact $(arg)"

phpstan:
	@make dcr cmd="vendor/bin/phpstan --memory-limit=1G $(arg)"

composer:
	@make dcr cmd="composer $(arg)"

csfix:
	@make dcr cmd="vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php"