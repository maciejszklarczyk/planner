# herald

Dev CLI for the Plan project. Wraps both Docker Compose stacks (backend + frontend) into one command.

## Commands

| Command | Description |
|---------|-------------|
| `herald up` | Start backend + frontend containers |
| `herald down` | Stop and remove containers (frontend removes volumes too) |
| `herald restart` | `down` then `up` |
| `herald db-reset` | Reload fixtures into the database (runs inside `planner-php`) |
| `herald status` | List running containers with status and ports |
| `herald health` | HTTP check backend (`localhost:8000/api`) and frontend (`localhost:3000`) |
| `herald test` | Run backend test suite (`composer run-tests` inside Docker with `.env.test`) |
| `herald dirty` | Show uncommitted changes and unpushed commits across all repos (backend, frontend, bruno, herald) |
| `herald cleanup` | `git reset --hard` in every repo — prompts for confirmation first |
| `herald help` | Show available commands (also `--help`, `-h`; default when no args given) |

## Install globally

Symlink `herald.sh` into a directory on your `$PATH` so you can run `herald` from anywhere.

```bash
ln -s /absolute/path/to/plan/herald/herald.sh /usr/local/bin/herald
chmod +x /usr/local/bin/herald
```

Example for this repo:

```bash
ln -s ~/Projects/plan/herald/herald.sh /usr/local/bin/herald
```

The script resolves symlinks itself (`readlink` loop at the top), so it always finds the project root relative to the real file location — not the symlink location.

Verify:

```bash
herald status
```

## Notes

- Requires Docker with the Compose plugin.
- Backend runs on `http://localhost:8000`, frontend on `http://localhost:3000`.
- `db-reset` assumes the container `planner-php` is running.
