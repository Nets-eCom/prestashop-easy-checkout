# MCP payment tools

Nexi Checkout optionally exposes payment tools to AI clients through the official
PrestaShop MCP Server module (`ps_mcp_server`).

## Setup

1. Install and configure PrestaShop MCP Server using its official
   [installation guide](https://docs.mcp.prestashop.com/en/0-getting-started/installation/)
   and [setup guide](https://docs.mcp.prestashop.com/en/0-getting-started/setup/).
2. Clear PrestaShop's cache and run manual tool discovery in MCP Server's developer options.
3. Under **Modules & Features**, enable Nexi Checkout and the tools you want to use.
4. Authorize a member: **Viewer** can read payment details; **Editor** is required
   for capture, refund, and cancellation.
5. Connect your AI client using MCP Server's URL and authentication settings.

## Available tools

Each call handles one order in the current shop. In multistore installations, use
the relevant shop's MCP endpoint.

| Tool | Required arguments | Action |
| --- | --- | --- |
| `nexi_payment_details` | `orderId` | Get live payment status, currency, balances, charges, and items. |
| `nexi_capture_payment` | `orderId`, `amount` | Capture the specified amount of reserved funds. |
| `nexi_refund_payment` | `orderId`, `amount` | Refund the specified amount of captured funds. |
| `nexi_cancel_payment` | `orderId` | Cancel an untouched reservation. |

Amounts must be positive integers in **minor currency units**: `1250` means
EUR 12.50. For example, to capture EUR 12.50:

```json
{ "orderId": 123, "amount": 1250 }
```

For a full capture or refund, retrieve payment details and explicitly supply the
remaining eligible balance. Amounts exceeding that balance or ineligible payment
states produce tool errors.

You can also ask your AI client: “Show the Nexi payment details for order 123.”
