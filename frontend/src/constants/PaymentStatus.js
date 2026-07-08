class PaymentStatus {
  static CHARGED = 'charged'
  static PARTIALLY_CHARGED = 'partially_charged'
  static PENDING_REFUND = 'pending_refund'
  static REFUNDED = 'refunded'
  static PARTIALLY_REFUNDED = 'partially_refunded'
  static CANCELLED = 'cancelled'
  static NEW = 'new'
  static RESERVED = 'reserved'

  static BADGE_CLASSES = {
    [PaymentStatus.CHARGED]: 'badge-success',
    [PaymentStatus.PARTIALLY_CHARGED]: 'badge-success',
    [PaymentStatus.PENDING_REFUND]: 'badge-warning',
    [PaymentStatus.REFUNDED]: 'badge-success',
    [PaymentStatus.PARTIALLY_REFUNDED]: 'badge-success',
    [PaymentStatus.CANCELLED]: 'badge-danger',
    [PaymentStatus.NEW]: 'badge-secondary',
    [PaymentStatus.RESERVED]: 'badge-secondary'
  }

  static DEFAULT_BADGE_CLASS = 'badge-secondary'

  static getBadgeClass(status) {
    return PaymentStatus.BADGE_CLASSES[status] || PaymentStatus.DEFAULT_BADGE_CLASS
  }

  static getTcString(status) {
    if (!status) {
      return 'Undefined';
    }

    return `nexi-checkout-payment-component.payment-details.status.${status}`;
  }

  static isCancellable(status) {
    return status === PaymentStatus.RESERVED
  }
}

export default PaymentStatus
