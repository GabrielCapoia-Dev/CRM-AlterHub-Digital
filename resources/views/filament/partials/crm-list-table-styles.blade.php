@once
    <style>
        .fi-ta-record.crm-list-record,
        .crm-list-record .fi-ta-record-content,
        .crm-list-record .fi-ta-grid,
        .crm-list-record .fi-ta-split,
        .crm-list-record .fi-ta-stack {
            max-width: 100%;
            min-width: 0;
        }

        .fi-ta-record.crm-list-record {
            border: 1px solid rgba(212, 216, 230, 0.92);
            border-radius: 0.5rem;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(244, 245, 249, 0.96)),
                linear-gradient(135deg, rgba(58, 109, 214, 0.035), rgba(15, 23, 41, 0.025));
            box-shadow: 0 1px 2px rgba(15, 23, 41, 0.04), 0 10px 24px rgba(15, 23, 41, 0.055);
            transition: border-color 140ms ease, box-shadow 140ms ease, transform 140ms ease;
        }

        .fi-ta-record.crm-list-record:hover {
            border-color: rgba(58, 109, 214, 0.28);
            box-shadow: 0 2px 5px rgba(15, 23, 41, 0.06), 0 14px 30px rgba(15, 23, 41, 0.08);
        }

        .crm-list-record .fi-ta-record-content {
            display: grid;
            gap: 0.75rem;
            width: 100%;
            padding: 0.85rem;
        }

        .crm-list-top.fi-ta-split {
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.65rem 1rem;
            padding-bottom: 0.7rem;
            border-bottom: 1px solid rgba(212, 216, 230, 0.86);
        }

        .crm-list-main {
            min-width: 0;
        }

        .crm-list-meta,
        .crm-list-finance,
        .crm-list-flow {
            gap: 0.65rem 0.85rem;
        }

        .crm-list-footer {
            padding-top: 0.7rem;
            border-top: 1px solid rgba(212, 216, 230, 0.78);
        }

        .crm-list-title .fi-ta-text-item {
            color: rgb(15 23 41);
            font-weight: 700;
            line-height: 1.35;
        }

        .crm-list-code .fi-ta-text-item,
        .crm-list-id .fi-ta-text-item {
            font-variant-numeric: tabular-nums;
        }

        .crm-list-field .fi-ta-text-description,
        .crm-list-title .fi-ta-text-description {
            color: rgb(107 118 148);
            font-size: 0.75rem;
            line-height: 1rem;
            font-weight: 600;
            letter-spacing: 0;
        }

        .crm-list-field .fi-ta-text-item,
        .crm-list-field .fi-badge,
        .crm-list-title .fi-ta-text-item,
        .crm-list-title .fi-ta-text-description,
        .crm-list-record .fi-ta-text-item,
        .crm-list-record .fi-ta-text-description {
            max-width: 100%;
            overflow-wrap: anywhere;
            white-space: normal;
        }

        .crm-list-number .fi-ta-text-item,
        .crm-list-money .fi-ta-text-item {
            font-variant-numeric: tabular-nums;
            font-weight: 700;
        }

        .crm-list-status .fi-badge,
        .crm-list-impact .fi-badge,
        .crm-list-stock .fi-badge {
            border: 1px solid rgba(212, 216, 230, 0.72);
        }

        .crm-list-record .fi-ta-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.55rem;
            margin-top: 0.15rem;
            padding-top: 0.15rem;
        }

        .crm-list-record .fi-ta-actions .fi-btn,
        .crm-list-record .fi-ta-actions .fi-icon-btn {
            max-width: 100%;
        }

        .crm-list-record .fi-ta-actions .fi-btn {
            min-height: 2.35rem;
            padding: 0.48rem 0.95rem;
            border-radius: 0.7rem;
            font-size: 0.84rem;
            font-weight: 600;
            gap: 0.45rem;
        }

        .crm-list-record .fi-ta-actions .fi-ac-btn-group {
            display: flex;
            flex-wrap: wrap;
            gap: 0.55rem;
        }

        @media (max-width: 48rem) {
            .crm-list-record .fi-ta-record-content {
                gap: 0.65rem;
                padding: 0.75rem;
            }

            .crm-list-top.fi-ta-split {
                align-items: stretch;
            }

            .crm-list-record .fi-ta-actions {
                gap: 0.5rem;
            }

            .crm-list-record .fi-ta-actions,
            .crm-list-record .fi-ta-actions .fi-btn {
                width: 100%;
            }

            .crm-list-record .fi-ta-actions .fi-btn {
                justify-content: center;
            }
        }
    </style>
@endonce
