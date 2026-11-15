// assets/objects/animals/dog_shepherd.js
export default {
    id: 'dog_shepherd',
    name: 'Собака: Овчарка',
    category: 'animals',
    radius: 0.35,
    schema: [
        { key: 'scale', label: 'Размер', type: 'range', min: 0.7, max: 1.5, step: 0.1, default: 1.0 },
        { key: 'color', label: 'Основной окрас', type: 'color', default: '#b45309' },
        { key: 'accent', label: 'Второй оттенок', type: 'color', default: '#111827' }
    ],
    build(d) {
        const g = new THREE.Group();
        const s = parseFloat(d.scale || 1.0);
        const mainMat = new THREE.MeshStandardMaterial({ color: d.color || '#b45309', roughness: 0.8 });
        const accMat = new THREE.MeshStandardMaterial({ color: d.accent || '#111827', roughness: 0.8 });
        const eyeMat = new THREE.MeshBasicMaterial({ color: 0x111827 });

        const bodyH = 0.34;
        const bodyL = 0.64;

        const body = new THREE.Mesh(new THREE.BoxGeometry(0.26, 0.26, bodyL), mainMat);
        body.position.y = bodyH; g.add(body);
        

        const head = new THREE.Group();
        head.name = 'head';
        head.position.set(0, bodyH + 0.2, bodyL * 0.48);
        const skull = new THREE.Mesh(new THREE.BoxGeometry(0.22, 0.20, 0.22), mainMat);
        const snout = new THREE.Mesh(new THREE.BoxGeometry(0.12, 0.09, 0.12), accMat);
        snout.position.set(0, -0.04, 0.14);
        [-0.055, 0.055].forEach(ex => {
            const eye = new THREE.Mesh(new THREE.SphereGeometry(0.022, 8, 8), eyeMat);
            eye.position.set(ex, 0.03, 0.115); head.add(eye);
        });
        head.add(skull, snout);
        g.add(head);

        const tail = new THREE.Mesh(new THREE.CylinderGeometry(0.025, 0.035, 0.3, 8), mainMat);
        tail.name = 'rightArm';
        tail.position.set(0, bodyH + 0.1, -bodyL * 0.52);
        tail.rotation.x = -0.6; g.add(tail);

        [['leftLeg', -0.08, bodyL * 0.35], ['rightLeg', 0.08, bodyL * 0.35], ['backL', -0.08, -bodyL * 0.35], ['backR', 0.08, -bodyL * 0.35]].forEach(([nm, lx, lz]) => {
            const lg = new THREE.Group(); lg.name = nm;
            lg.position.set(lx, bodyH - 0.06, lz);
            const lm = new THREE.Mesh(new THREE.CylinderGeometry(0.035, 0.03, bodyH - 0.06, 8), mainMat);
            lm.position.y = -(bodyH - 0.06) / 2; lg.add(lm); g.add(lg);
        });

        g.scale.set(s, s, s);
        g.traverse(m => { if (m.isMesh) { m.castShadow = true; m.receiveShadow = true; } });
        return g;
    }
};
