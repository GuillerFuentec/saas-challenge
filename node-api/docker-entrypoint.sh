#!/bin/sh
set -euo pipefail

npx wait-port mysql:3306 --timeout=600000
npm run prepare
npm run migrate
npm run seed
npm run start