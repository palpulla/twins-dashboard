export const JOB_TYPES = {
  DOOR_INSTALL: 'Door Install',
  REPAIR: 'Repair',
  OPENER_INSTALL: 'Opener Install',
  OPENER_REPAIR: 'Opener + Repair',
  DOOR_OPENER_INSTALL: 'Door + Opener Install',
  SERVICE_CALL: 'Service Call',
  MAINTENANCE_VISIT: 'Maintenance Visit',
  WARRANTY_CALL: 'Warranty Call',
} as const;

export type JobType = (typeof JOB_TYPES)[keyof typeof JOB_TYPES];

export type JobCategory = 'installation' | 'repair' | 'maintenance' | 'warranty';

export const JOB_TYPE_CATEGORIES: Record<JobType, JobCategory> = {
  [JOB_TYPES.DOOR_INSTALL]: 'installation',
  [JOB_TYPES.REPAIR]: 'repair',
  [JOB_TYPES.OPENER_INSTALL]: 'installation',
  [JOB_TYPES.OPENER_REPAIR]: 'repair',
  [JOB_TYPES.DOOR_OPENER_INSTALL]: 'installation',
  [JOB_TYPES.SERVICE_CALL]: 'repair',
  [JOB_TYPES.MAINTENANCE_VISIT]: 'maintenance',
  [JOB_TYPES.WARRANTY_CALL]: 'warranty',
};

export const INSTALLATION_JOB_TYPES: JobType[] = [
  JOB_TYPES.DOOR_INSTALL,
  JOB_TYPES.OPENER_INSTALL,
  JOB_TYPES.DOOR_OPENER_INSTALL,
];

export const REPAIR_JOB_TYPES: JobType[] = [
  JOB_TYPES.REPAIR,
  JOB_TYPES.SERVICE_CALL,
  JOB_TYPES.OPENER_REPAIR,
];

export const NEW_DOOR_JOB_TYPES: JobType[] = [
  JOB_TYPES.DOOR_INSTALL,
  JOB_TYPES.DOOR_OPENER_INSTALL,
];

export const WARRANTY_JOB_TYPES: JobType[] = [
  JOB_TYPES.WARRANTY_CALL,
];
