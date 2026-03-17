# Sistema de Agendamento Acadêmico UNIFAP

Sistema de gerenciamento de agendamentos acadêmicos desenvolvido em Laravel 11 com Blade, Tailwind CSS e Livewire.

## 🚀 Como Rodar

### Pré-requisitos

- PHP 8.2+
- Composer
- Node.js 18+
- SQLite (já incluído no PHP)

### Instalação

1. **Clone o repositório**
   ```bash
   git clone <seu-repo>
   cd Agendamento
   ```

2. **Instale as dependências PHP**
   ```bash
   composer install
   ```

3. **Instale as dependências Node.js**
   ```bash
   npm install
   ```

4. **Configure o arquivo .env**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Execute as migrações**
   ```bash
   php artisan migrate
   ```

6. **Execute os seeders** (opcional)
   ```bash
   php artisan db:seed
   ```

7. **Crie o symlink de armazenamento**
   ```bash
   php artisan storage:link
   ```

8. **Compile os assets (CSS/JS)**
   ```bash
   npm run build
   ```

### Desenvolvimento

Para desenvolvimento com hot reload:

```bash
npm run dev
```

Em outro terminal, inicie o servidor Laravel:

```bash
php artisan serve
```

O aplicativo estará disponível em `http://localhost:8000`

## 👥 Usuários de Teste

### Aluno
- **E-mail:** `aluno@unifap.edu.br`
- **Senha:** `password`

### Atendente
- **E-mail:** `atendente@unifap.edu.br`
- **Senha:** `password`

## 📁 Estrutura do Projeto

```
├── app/
│   ├── Actions/          # Ações de Fortify (autenticação)
│   ├── Http/Controllers/ # Controllers da aplicação
│   ├── Models/           # Modelos Eloquent
│   └── Concerns/         # Traits compartilhadas
├── resources/
│   ├── views/            # Templates Blade
│   ├── js/               # JavaScript
│   └── css/              # Tailwind CSS
├── routes/               # Definição de rotas
├── database/
│   ├── migrations/       # Migrações do banco
│   └── seeders/          # Seeders
└── config/               # Configurações da aplicação
```

## 🔧 Comandos Úteis

```bash
# Limpar cache de configuração
php artisan config:clear

# Resetar banco de dados com seeders
php artisan migrate:fresh --seed

# Executar testes
php artisan test

# Verificar linting/estilo
php artisan pint --test

# Build para produção
npm run build
```

## 🎨 Tecnologias Utilizadas

- **Laravel 11** - Framework PHP
- **Blade** - Template engine
- **Tailwind CSS v4** - Utility-first CSS
- **Vite** - Build tool
- **Alpine.js** - Componentes interativos simples
- **SQLite** - Banco de dados local
- **Laravel Fortify** - Autenticação

## 📝 Notas

- Mensagens de validação e autenticação estão em **português**
- O banco de dados é SQLite (arquivo `database/database.sqlite`)
- Fotos de perfil são armazenadas em `storage/app/public/profile-photos/`

## 📄 Licença

MIT
