# define gateway

resource "aws_api_gateway_rest_api" "gateway" {
  name = "${var.lambda_name}-api-gateway"
  description = "Gateway for hryvna telegram bot"
}

resource "aws_api_gateway_resource" "resource" {
  rest_api_id = "${aws_api_gateway_rest_api.gateway.id}"
  parent_id = "${aws_api_gateway_rest_api.gateway.root_resource_id}"
  path_part = "${var.lambda_name}"
}

resource "aws_api_gateway_method" "method" {
  rest_api_id = "${aws_api_gateway_rest_api.gateway.id}"
  resource_id = "${aws_api_gateway_resource.resource.id}"
  http_method = "ANY"
  authorization = "NONE"
}

# connect to lambda

resource "aws_api_gateway_integration" "lambda" {
  rest_api_id = "${aws_api_gateway_rest_api.gateway.id}"
  resource_id = "${aws_api_gateway_method.method.resource_id}"
  http_method = "${aws_api_gateway_method.method.http_method}"

  integration_http_method = "ANY"
  type = "AWS_PROXY"
  uri = "${aws_lambda_function.lambda.invoke_arn}"
}

resource "aws_api_gateway_deployment" "lambda" {
  depends_on = [
    "aws_api_gateway_integration.lambda"
  ]

  rest_api_id = "${aws_api_gateway_rest_api.gateway.id}"
  stage_name = "live"
}

resource "aws_lambda_permission" "apigw" {
  statement_id = "AllowAPIGatewayInvoke"
  action = "lambda:InvokeFunction"
  function_name = "${aws_lambda_function.lambda.arn}"
  principal = "apigateway.amazonaws.com"

  source_arn = "${aws_api_gateway_deployment.lambda.execution_arn}/*/*"
}
