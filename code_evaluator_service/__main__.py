from uvicorn import run


if __name__ == "__main__":
    run("code_evaluator_service.evaluator_app:app", host="0.0.0.0", port=5055, reload=False)
