from __future__ import annotations

import logging
import time
from logging.handlers import RotatingFileHandler
from pathlib import Path

from fastapi import FastAPI, Request
from fastapi.responses import JSONResponse

LOG_DIR = Path("logs")
LOG_DIR.mkdir(exist_ok=True)
LOG_FILE = LOG_DIR / "requests.log"

logger = logging.getLogger("request_logger")
logger.setLevel(logging.INFO)
if not logger.handlers:
    file_handler = RotatingFileHandler(
        LOG_FILE,
        maxBytes=5 * 1024 * 1024,
        backupCount=3,
        encoding="utf-8",
    )
    formatter = logging.Formatter(
        fmt="%(asctime)s [%(levelname)s] %(message)s",
        datefmt="%Y-%m-%d %H:%M:%S",
    )
    file_handler.setFormatter(formatter)
    logger.addHandler(file_handler)
    logger.propagate = False

app = FastAPI(title="pkf_nb service")


@app.middleware("http")
async def log_requests(request: Request, call_next):
    start_time = time.perf_counter()
    response = await call_next(request)
    duration_ms = (time.perf_counter() - start_time) * 1000
    user_agent = request.headers.get("user-agent", "-")
    client_host = request.client.host if request.client else "-"

    logger.info(
        "%s %s -> %s | %.2fms | UA=%s | IP=%s",
        request.method,
        request.url.path,
        response.status_code,
        duration_ms,
        user_agent,
        client_host,
    )

    return response


@app.get("/")
async def healthcheck():
    return JSONResponse({"status": "ok"})


@app.get("/echo")
async def echo(query: str | None = None):
    return JSONResponse({"echo": query})
