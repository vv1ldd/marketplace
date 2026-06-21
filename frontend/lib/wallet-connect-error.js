export class WalletConnectError extends Error {
  constructor(code, userMessage, cause = null) {
    super(userMessage);
    this.name = 'WalletConnectError';
    this.code = code;
    this.userMessage = userMessage;
    this.cause = cause;
  }
}
