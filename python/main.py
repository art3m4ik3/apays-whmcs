from flask import Flask, request, jsonify
from dotenv import load_dotenv
import hashlib
import httpx
import os

load_dotenv()

app = Flask(__name__)


@app.route("/", methods=["POST"])
def process_request():
    try:
        data = request.json
        order_id = data.get("order_id")
        status = data.get("status")

        generated_sign = hashlib.md5(
            f"{order_id}:{status}:{os.getenv("SECRET_KEY")}".encode()
        ).hexdigest()

        params = {"order_id": order_id, "status": status, "sign": generated_sign}

        response = httpx.get(
            os.getenv("WHMCS_URL"),
            params=params,
            headers={
                "User-Agent": "Apays Request Forward System/v1.0.0"
            },
        )

        if response.status_code == 200:
            return (
                jsonify(
                    {
                        "message": "Request processed successfully",
                        "response": response.text,
                    }
                ),
                200,
            )
        else:
            return (
                jsonify(
                    {"error": "Failed to process request", "response": response.text}
                ),
                response.status_code,
            )
    except Exception as e:
        return jsonify({"error": str(e)}), 500


if __name__ == "__main__":
    app.run(port=3001, debug=True)
