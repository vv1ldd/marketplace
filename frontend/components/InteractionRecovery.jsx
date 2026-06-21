'use client';

import { useEffect } from 'react';
import { bindInteractionRecovery } from '../lib/interaction-recovery';

export function InteractionRecovery() {
  useEffect(() => bindInteractionRecovery(), []);

  return null;
}
