import React, { useEffect, useState } from 'react';
import QRCode from 'qrcode';
import { Box, CircularProgress } from '@mui/material';

const TwoFactorQrCode = ({ value, size = 220 }) => {
    const [src, setSrc] = useState('');

    useEffect(() => {
        let active = true;

        const generate = async () => {
            if (!value) {
                setSrc('');
                return;
            }

            const dataUrl = await QRCode.toDataURL(value, {
                margin: 1,
                width: size,
                color: {
                    dark: '#e2e8f0',
                    light: '#111827',
                },
            });

            if (active) {
                setSrc(dataUrl);
            }
        };

        generate().catch(() => {
            if (active) {
                setSrc('');
            }
        });

        return () => {
            active = false;
        };
    }, [size, value]);

    if (!src) {
        return (
            <Box sx={{ width: size, height: size, display: 'grid', placeItems: 'center', borderRadius: 2, bgcolor: 'rgba(255,255,255,0.03)' }}>
                <CircularProgress size={24} />
            </Box>
        );
    }

    return (
        <Box
            component="img"
            src={src}
            alt="Two-factor setup QR code"
            sx={{
                width: size,
                height: size,
                borderRadius: 2,
                border: '1px solid rgba(255,255,255,0.08)',
                bgcolor: '#111827',
                p: 1,
            }}
        />
    );
};

export default TwoFactorQrCode;
