# Login SDD

## Requisitos
- PHP 8.1+
- MySQL/MariaDB
- Servidor servindo via HTTPS em produção

## Instalação
1. Crie o banco e tabelas: `mysql -u root -p < db.sql`
2. Ajuste credenciais em `config.php` (`DB_*`).
3. Crie um usuário:
   ```bash
   php seed_user.php demo@sdd.test 'SenhaForte!123'
