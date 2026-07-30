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

        .oa-photo-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 28rem), 1fr));
            gap: 1rem;
            width: 100%;
        }

        .oa-photo-card {
            min-width: 0;
            overflow: hidden;
            border: 1px solid rgba(212, 216, 230, 0.95);
            border-radius: 1rem;
            background: rgb(255 255 255);
            box-shadow: 0 10px 28px rgba(15, 23, 41, 0.08);
        }

        .oa-photo-card__preview {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: clamp(18rem, 52vh, 38rem);
            padding: 1rem;
            overflow: hidden;
            background-color: rgb(244 245 249);
            background-image:
                linear-gradient(45deg, rgba(212, 216, 230, 0.2) 25%, transparent 25%),
                linear-gradient(-45deg, rgba(212, 216, 230, 0.2) 25%, transparent 25%),
                linear-gradient(45deg, transparent 75%, rgba(212, 216, 230, 0.2) 75%),
                linear-gradient(-45deg, transparent 75%, rgba(212, 216, 230, 0.2) 75%);
            background-position: 0 0, 0 0.5rem, 0.5rem -0.5rem, -0.5rem 0;
            background-size: 1rem 1rem;
        }

        .oa-photo-card__image {
            display: block;
            width: 100%;
            height: 100%;
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            object-position: center;
            image-orientation: from-image;
        }

        .oa-photo-card__hint {
            position: absolute;
            right: 0.75rem;
            bottom: 0.75rem;
            padding: 0.4rem 0.65rem;
            border: 1px solid rgba(255, 255, 255, 0.28);
            border-radius: 999px;
            color: rgb(255 255 255);
            background: rgba(15, 23, 41, 0.78);
            box-shadow: 0 4px 14px rgba(15, 23, 41, 0.18);
            font-size: 0.72rem;
            font-weight: 600;
            line-height: 1.2;
            pointer-events: none;
        }

        .oa-photo-card__details {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.1rem;
            border-top: 1px solid rgba(212, 216, 230, 0.85);
        }

        .oa-photo-card__copy {
            display: grid;
            min-width: 0;
            gap: 0.28rem;
        }

        .oa-photo-card__title {
            color: rgb(15 23 41);
            font-size: 0.9rem;
            line-height: 1.35;
            overflow-wrap: anywhere;
        }

        .oa-photo-card__meta {
            color: rgb(107 118 148);
            font-size: 0.78rem;
            line-height: 1.35;
            overflow-wrap: anywhere;
        }

        .oa-photo-card__download {
            flex: 0 0 auto;
            padding: 0.5rem 0.75rem;
            border: 1px solid rgba(58, 109, 214, 0.28);
            border-radius: 0.65rem;
            color: rgb(30 69 168);
            background: rgb(234 241 253);
            font-size: 0.78rem;
            font-weight: 700;
            text-decoration: none;
        }

        .oa-photo-card__download:hover {
            border-color: rgba(30, 69, 168, 0.5);
            background: rgb(208 224 250);
        }

        .oa-photo-gallery__empty {
            padding: 2rem;
            border: 1px dashed rgb(180 186 206);
            border-radius: 1rem;
            color: rgb(107 118 148);
            background: rgb(244 245 249);
            text-align: center;
            font-size: 0.88rem;
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

            .oa-photo-card__preview {
                height: clamp(16rem, 48vh, 30rem);
                padding: 0.65rem;
            }

            .oa-photo-card__hint {
                display: none;
            }

            .oa-photo-card__details {
                align-items: stretch;
                flex-direction: column;
            }

            .oa-photo-card__download {
                text-align: center;
            }
        }
    </style>
@endonce
