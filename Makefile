.PHONY: qa qa-fix qa-analyse qa-test help

help: ## Affiche les commandes disponibles
	@grep -E '^[a-zA-Z0-9_.-]+:.*?## .+$$' Makefile \
		| awk -F':.*## ' '{printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'

qa: qa-fix qa-analyse qa-test ## Pipeline QA complet (fix + analyse + tests)

qa-fix: ## Auto-fix code style (PHP-CS-Fixer)
	@printf "PHP-CS-Fixer... "
	@vendor/bin/php-cs-fixer fix -q
	@echo "done"

qa-analyse: ## Analyse statique (PHPStan + Deptrac)
	@echo "PHPStan (level 6)..."
	@vendor/bin/phpstan analyse --no-progress --memory-limit=512M
	@echo ""
	@echo "Deptrac (architecture DDD)..."
	@vendor/bin/deptrac --no-progress

qa-test: ## Tests unitaires
	@vendor/bin/phpunit
