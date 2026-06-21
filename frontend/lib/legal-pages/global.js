export const companyDetails = {
  brand: process.env.NEXT_PUBLIC_ACQUIRING_COMPANY_BRAND || 'Meanly',
  legalName: process.env.NEXT_PUBLIC_ACQUIRING_COMPANY_LEGAL_NAME || 'Meanly Systems',
  country: process.env.NEXT_PUBLIC_ACQUIRING_COMPANY_COUNTRY || 'United States',
  legalAddress: process.env.NEXT_PUBLIC_ACQUIRING_COMPANY_LEGAL_ADDRESS || 'Add the registered business address before publishing production legal pages.',
  actualAddress: process.env.NEXT_PUBLIC_ACQUIRING_COMPANY_ACTUAL_ADDRESS || 'Add the operating address before publishing production legal pages.',
  phone: process.env.NEXT_PUBLIC_ACQUIRING_COMPANY_PHONE || '+1 (000) 000-0000',
  email: process.env.NEXT_PUBLIC_ACQUIRING_COMPANY_EMAIL || 'support@meanly.one',
  acquiringBank: process.env.NEXT_PUBLIC_ACQUIRING_BANK_NAME || 'payment processor agreed during onboarding',
  sslLevel: process.env.NEXT_PUBLIC_ACQUIRING_SSL_LEVEL || 'TLS 1.2 or higher',
  paymentSystems: (process.env.NEXT_PUBLIC_ACQUIRING_PAYMENT_SYSTEMS || 'Visa,Mastercard,American Express')
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean),
  showTaxIds: false,
  requisitesTitle: 'Seller details and payments',
  requisitesNote: 'Replace NEXT_PUBLIC_ACQUIRING_COMPANY_* and NEXT_PUBLIC_ACQUIRING_BANK_NAME with approved legal data before production launch.',
  navLabel: 'Legal documents',
  labels: {
    brand: 'Brand',
    legalName: 'Legal entity',
    country: 'Country',
    legalAddress: 'Registered address',
    actualAddress: 'Operating address',
    phone: 'Phone',
    email: 'Email',
    acquiringBank: 'Payments',
    sslLevel: 'HTTPS',
    paymentSystems: 'Cards',
  },
};

export const legalPages = {
  company: {
    href: '/company',
    title: 'Company information',
    description: 'Seller contact details, registration country, and storefront disclosures for Meanly.',
    eyebrow: 'Company',
    sections: [
      {
        title: 'Customer support',
        items: [
          'Buyers can reach support through the phone number and email listed in the seller details.',
          'Order inquiries should include the order number, email address, or Vault identifier.',
          'Meanly publishes seller information openly so buyers know who fulfills the purchase.',
        ],
      },
      {
        title: 'Store policies',
        items: [
          'Terms of sale, privacy policy, payment rules, digital delivery rules, and refund policy are published on this site.',
          'Replace placeholder seller details in NEXT_PUBLIC_ACQUIRING_COMPANY_* before going live.',
        ],
      },
    ],
  },
  payment: {
    href: '/payment',
    title: 'Card payments',
    description: 'How card payments work, payment security, and interaction with the payment processor.',
    eyebrow: 'Payments',
    sections: [
      {
        title: 'Checkout flow',
        items: [
          'The buyer selects a digital product, reviews price, activation region, and delivery method.',
          'After order confirmation, the site redirects the buyer to a secure payment page operated by the payment processor.',
          'Card details are entered on the payment page. Meanly does not store full card numbers, CVV/CVC values, or card credentials.',
          'After successful authorization, the order is marked paid and the digital code or delivery instructions appear in Vault and/or email.',
        ],
      },
      {
        title: 'Payment security',
        items: [
          'Checkout and account pages are served over HTTPS with industry-standard TLS.',
          'The payment processor may apply 3-D Secure, fraud monitoring, and additional checks required by card networks.',
          'Suspicious transactions may be declined or sent for manual review to protect the cardholder.',
        ],
      },
      {
        title: 'Card networks',
        items: [
          'Payment network logos are shown only after onboarding and only in the official form provided by the processor or network.',
          'Available card networks depend on the merchant agreement with the payment processor.',
        ],
      },
    ],
  },
  delivery: {
    href: '/delivery',
    title: 'Digital delivery',
    description: 'Delivery timing and methods for digital goods after payment.',
    eyebrow: 'Delivery',
    sections: [
      {
        title: 'Delivery timing',
        items: [
          'Most digital goods are delivered automatically within seconds after payment confirmation.',
          'If a supplier performs additional checks, delivery may take longer. Order status is shown on the order page and in Vault.',
          'If delivery is delayed for technical reasons, contact support with the order number.',
        ],
      },
      {
        title: 'Delivery methods',
        items: [
          'Digital codes, vouchers, activation keys, or instructions appear in the buyer secure Vault.',
          'When enabled for a product, order details may also be sent to the buyer email address.',
          'Physical shipping does not apply to digital goods sold on Meanly.',
        ],
      },
    ],
  },
  refund: {
    href: '/refund',
    title: 'Refunds and cancellations',
    description: 'Refund rules and handling of issues with digital codes.',
    eyebrow: 'Refunds',
    sections: [
      {
        title: 'When a refund is available',
        items: [
          'A refund may be issued if the digital good was not delivered, was delivered incorrectly, or does not match the product page.',
          'If a valid code has already been revealed to the buyer, refunds may be limited by digital-goods policy and applicable law.',
          'Duplicate or erroneous charges are reviewed by support and refunded after payment verification.',
        ],
      },
      {
        title: 'How refunds are processed',
        items: [
          'Card refunds are returned to the same card used for the original payment whenever possible.',
          'Posting time depends on the issuing bank and card network rules.',
          'Include the order number, payment date, email, and a short description of the issue when contacting support.',
        ],
      },
    ],
  },
  offer: {
    href: '/offer',
    title: 'Terms of sale',
    description: 'Core terms for selling digital goods to Meanly buyers.',
    eyebrow: 'Terms',
    sections: [
      {
        title: 'Scope',
        items: [
          'The seller provides digital goods such as gift cards, top-up codes, game keys, subscriptions, and other digital vouchers.',
          'Description, price, currency, activation region, and product characteristics are shown on the product page before payment.',
          'By placing an order, the buyer accepts the terms of sale, payment rules, delivery rules, and refund policy.',
        ],
      },
      {
        title: 'Price and fulfillment',
        items: [
          'The final price is shown before the buyer proceeds to payment.',
          'Fulfillment is complete once the buyer receives the digital code, voucher, or instructions in Vault and/or by email.',
          'The buyer must verify region, platform, and activation restrictions before paying.',
        ],
      },
    ],
  },
  privacy: {
    href: '/privacy',
    title: 'Privacy policy',
    description: 'How Meanly handles personal data, cookies, and payment-related information.',
    eyebrow: 'Privacy',
    sections: [
      {
        title: 'Data we process',
        items: [
          'Order processing may include email, order details, session identifiers, and information needed for support.',
          'Sign-in uses Meanly One / Simple Layer Identity. Meanly receives verified identity results, not the user password.',
          'Full card details are entered on the payment processor page and are not stored by Meanly.',
        ],
      },
      {
        title: 'Why we process data',
        items: [
          'To fulfill orders, deliver digital goods, provide support, prevent fraud, and comply with legal obligations.',
          'Cookies support session continuity, security, language preferences, and service quality analytics.',
          'Buyers may contact support to request correction or deletion of data where applicable law allows.',
        ],
      },
    ],
  },
  terms: {
    href: '/terms',
    title: 'Terms of use',
    description: 'Rules for using the site, Vault, and Meanly services.',
    eyebrow: 'Terms',
    sections: [
      {
        title: 'Using the site',
        items: [
          'Users must provide accurate contact details and must not use the site for unlawful activity.',
          'Bypassing security controls, credential stuffing, refund abuse, and other prohibited conduct are not allowed.',
          'Meanly may restrict access or transactions when fraud, sanctions, or product-rule violations are detected.',
        ],
      },
      {
        title: 'Content and links',
        items: [
          'The site does not publish links to prohibited categories such as illegal gambling, drugs, weapons, pornography, or financial pyramids.',
          'Catalog content is reviewed for accurate descriptions, pricing, links, and product availability.',
          'Report incorrect content or broken links to support.',
        ],
      },
    ],
  },
};
