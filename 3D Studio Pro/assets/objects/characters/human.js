// objects/characters/human.js — 3D модель ресурса
export function buildCharacter3D(d) {
            const g = new THREE.Group();
            const isFemale = d.gender === 'female';
            const physiqueMap = { slim: 0.82, average: 1.0, athletic: 1.15, heavy: 1.32 };
            const wScale = physiqueMap[d.physique] || 1.0;
            const hScale = parseFloat(d.height || 1.0);
            const acc = Array.isArray(d.accessories) ? d.accessories : [];
            const hasAcc = (key) => acc.includes(key);

            const skinMat = new THREE.MeshStandardMaterial({ color: d.skin || '#f5cba7', roughness: 0.55 });
            const topMat = new THREE.MeshStandardMaterial({ color: d.top || '#3b82f6', roughness: 0.7 });
            const botMat = new THREE.MeshStandardMaterial({ color: d.bottom || '#1e293b', roughness: 0.8 });
            const hairMat = new THREE.MeshStandardMaterial({ color: d.hairCol || '#2c1e16', roughness: 0.85 });
            const shoeMat = new THREE.MeshStandardMaterial({ color: d.shoesCol || '#f8fafc', roughness: 0.5 });
            const goldMat = new THREE.MeshStandardMaterial({ color: 0xfbbf24, metalness: 0.85, roughness: 0.2 });

            const bodyPivot = new THREE.Group();
            bodyPivot.name = 'bodyPivot';
            g.add(bodyPivot);

            const hipW = (isFemale ? 0.22 : 0.19) * wScale;
            const hips = new THREE.Mesh(new THREE.CylinderGeometry(hipW * 0.95, hipW, 0.16 * hScale, 14), botMat);
            hips.position.y = 0.54 * hScale;
            bodyPivot.add(hips);

            if (d.bottomType === 'skirt') {
                const skirt = new THREE.Mesh(new THREE.CylinderGeometry(hipW * 0.95, hipW * 1.45, 0.34 * hScale, 16), botMat);
                skirt.position.y = 0.42 * hScale;
                bodyPivot.add(skirt);
            }

            const shoulderW = (isFemale ? 0.20 : 0.26) * wScale;
            const waistW = (isFemale ? 0.15 : 0.20) * wScale;
            const torso = new THREE.Mesh(new THREE.CylinderGeometry(shoulderW, waistW, 0.48 * hScale, 14), topMat);
            torso.position.y = 0.84 * hScale;
            bodyPivot.add(torso);

            if (hasAcc('tie')) {
                const tieMat = new THREE.MeshStandardMaterial({ color: 0x991b1b, roughness: 0.5 });
                const knot = new THREE.Mesh(new THREE.BoxGeometry(0.045, 0.045, 0.03), tieMat);
                knot.position.set(0, 1.03 * hScale, shoulderW * 0.85);
                const blade = new THREE.Mesh(new THREE.ConeGeometry(0.038, 0.32 * hScale, 4), tieMat);
                blade.rotation.z = Math.PI;
                blade.rotation.y = Math.PI / 4;
                blade.position.set(0, 0.85 * hScale, shoulderW * 0.82);
                bodyPivot.add(knot, blade);
            }

            if (hasAcc('chain')) {
                const chain = new THREE.Mesh(new THREE.TorusGeometry(shoulderW * 0.55, 0.015, 8, 20), goldMat);
                chain.rotation.x = Math.PI * 0.38;
                chain.position.set(0, 1.03 * hScale, shoulderW * 0.42);
                bodyPivot.add(chain);
            }

            if (hasAcc('camera')) {
                const strapMat = new THREE.MeshStandardMaterial({ color: 0x111827 });
                const strap = new THREE.Mesh(new THREE.TorusGeometry(shoulderW * 0.62, 0.012, 6, 18), strapMat);
                strap.rotation.x = Math.PI * 0.35;
                strap.position.set(0, 0.98 * hScale, shoulderW * 0.38);

                const camGroup = new THREE.Group();
                const camBody = new THREE.Mesh(new THREE.BoxGeometry(0.15, 0.095, 0.065), new THREE.MeshStandardMaterial({ color: 0x1f2937, metalness: 0.6 }));
                const camLens = new THREE.Mesh(new THREE.CylinderGeometry(0.034, 0.038, 0.065, 14), new THREE.MeshStandardMaterial({ color: 0x090d16, metalness: 0.8 }));
                camLens.rotation.x = Math.PI / 2;
                camLens.position.z = 0.055;
                const lensGlass = new THREE.Mesh(new THREE.CircleGeometry(0.026, 12), new THREE.MeshBasicMaterial({ color: 0x38bdf8 }));
                lensGlass.position.z = 0.089;
                camGroup.add(camBody, camLens, lensGlass);
                camGroup.position.set(0, 0.82 * hScale, shoulderW * 0.88);
                bodyPivot.add(strap, camGroup);
            }

            if (hasAcc('pistol')) {
                const holster = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.14, 0.09), new THREE.MeshStandardMaterial({ color: 0x111827 }));
                holster.position.set(hipW + 0.02, 0.54 * hScale, 0.03);
                const pGrip = new THREE.Mesh(new THREE.BoxGeometry(0.035, 0.09, 0.045), new THREE.MeshStandardMaterial({ color: 0x1f2937 }));
                pGrip.position.set(hipW + 0.025, 0.62 * hScale, 0.01);
                pGrip.rotation.x = -0.35;
                bodyPivot.add(holster, pGrip);
            }

            const neck = new THREE.Mesh(new THREE.CylinderGeometry(0.065, 0.075, 0.1 * hScale, 10), skinMat);
            neck.position.y = 1.12 * hScale;
            bodyPivot.add(neck);

            const head = new THREE.Mesh(new THREE.SphereGeometry(0.20, 24, 24), skinMat);
            head.position.y = 1.30 * hScale;
            head.name = 'head';
            bodyPivot.add(head);

            const eyeWhite = new THREE.MeshBasicMaterial({ color: 0xffffff });
            const eyePupil = new THREE.MeshBasicMaterial({ color: 0x111827 });
            [-0.068, 0.068].forEach(ex => {
                const s = new THREE.Mesh(new THREE.SphereGeometry(0.032, 10, 10), eyeWhite);
                s.position.set(ex, 0.025, 0.175);
                const p = new THREE.Mesh(new THREE.SphereGeometry(0.018, 10, 10), eyePupil);
                p.position.set(ex, 0.025, 0.198);
                head.add(s, p);
            });
            const nose = new THREE.Mesh(new THREE.ConeGeometry(0.022, 0.055, 8), skinMat);
            nose.rotation.x = Math.PI / 2; nose.position.set(0, -0.01, 0.205);
            head.add(nose);

            const mouthMat = new THREE.MeshBasicMaterial({ color: isFemale ? 0xbe123c : 0x7f1d1d });
            if (d.expression === 'happy') {
                const smile = new THREE.Mesh(new THREE.TorusGeometry(0.045, 0.011, 8, 14, Math.PI), mouthMat);
                smile.rotation.x = Math.PI; smile.position.set(0, -0.055, 0.185);
                head.add(smile);
            } else if (d.expression === 'surprised') {
                const om = new THREE.Mesh(new THREE.TorusGeometry(0.025, 0.009, 8, 14), mouthMat);
                om.position.set(0, -0.065, 0.19); head.add(om);
            } else {
                const line = new THREE.Mesh(new THREE.BoxGeometry(0.065, 0.012, 0.015), mouthMat);
                line.position.set(0, -0.065, 0.19);
                if (d.expression === 'angry') line.rotation.z = 0.18;
                head.add(line);
            }

            if (hasAcc('mustache') || hasAcc('beard')) {
                const stache = new THREE.Mesh(new THREE.BoxGeometry(0.11, 0.024, 0.03), hairMat);
                stache.position.set(0, -0.042, 0.195);
                head.add(stache);
            }
            if (hasAcc('beard')) {
                const beard = new THREE.Mesh(new THREE.SphereGeometry(0.195, 16, 16, 0, Math.PI * 2, Math.PI * 0.55, Math.PI * 0.45), hairMat);
                beard.position.set(0, -0.02, 0.02);
                head.add(beard);
            }

            if (d.hair !== 'bald') {
                if (d.hair === 'mohawk') {
                    const mohawk = new THREE.Mesh(new THREE.BoxGeometry(0.055, 0.18, 0.34), hairMat);
                    mohawk.position.set(0, 0.21, -0.01);
                    head.add(mohawk);
                } else {
                    const capDome = new THREE.Mesh(new THREE.SphereGeometry(0.212, 20, 20, 0, Math.PI * 2, 0, Math.PI * 0.56), hairMat);
                    capDome.position.y = 0.015;
                    head.add(capDome);

                    if (d.hair === 'long' || d.hair === 'rocker') {
                        const backHair = new THREE.Mesh(new THREE.CylinderGeometry(0.21, 0.25, 0.48, 16, 1, false, Math.PI * 0.62, Math.PI * 1.76), hairMat);
                        backHair.position.set(0, -0.15, -0.02);
                        head.add(backHair);
                    } else if (d.hair === 'bob') {
                        const bob = new THREE.Mesh(new THREE.CylinderGeometry(0.215, 0.23, 0.24, 16, 1, false, Math.PI * 0.6, Math.PI * 1.8), hairMat);
                        bob.position.set(0, -0.06, -0.01);
                        head.add(bob);
                    } else if (d.hair === 'ponytail') {
                        const tail = new THREE.Mesh(new THREE.ConeGeometry(0.075, 0.34, 12), hairMat);
                        tail.position.set(0, -0.08, -0.24); tail.rotation.x = -0.35;
                        head.add(tail);
                    }
                }
            }

            if (hasAcc('cap')) {
                const capMat = new THREE.MeshStandardMaterial({ color: 0xdc2626 });
                const crown = new THREE.Mesh(new THREE.SphereGeometry(0.22, 18, 18, 0, Math.PI * 2, 0, Math.PI * 0.5), capMat);
                crown.position.y = 0.02;
                const visor = new THREE.Mesh(new THREE.CylinderGeometry(0.16, 0.18, 0.015, 14, 1, false, -Math.PI * 0.4, Math.PI * 0.8), capMat);
                visor.position.set(0, 0.05, 0.16);
                head.add(crown, visor);
            }
            if (hasAcc('beanie')) {
                const beanie = new THREE.Mesh(new THREE.SphereGeometry(0.228, 18, 18, 0, Math.PI * 2, 0, Math.PI * 0.58), new THREE.MeshStandardMaterial({ color: 0xd97706 }));
                beanie.position.y = 0.032;
                head.add(beanie);
            }
            if (hasAcc('hat')) {
                const hatMat = new THREE.MeshStandardMaterial({ color: 0x1f2937 });
                const brim = new THREE.Mesh(new THREE.CylinderGeometry(0.36, 0.36, 0.02, 20), hatMat);
                brim.position.y = 0.13;
                const topHat = new THREE.Mesh(new THREE.CylinderGeometry(0.19, 0.21, 0.18, 18), hatMat);
                topHat.position.y = 0.22;
                head.add(brim, topHat);
            }

            if (hasAcc('headphones')) {
                const hpMat = new THREE.MeshStandardMaterial({ color: 0x111827, metalness: 0.6 });
                const padMat = new THREE.MeshStandardMaterial({ color: 0x6366f1 });
                const band = new THREE.Mesh(new THREE.TorusGeometry(0.225, 0.018, 8, 22, Math.PI), hpMat);
                band.position.y = 0.03;
                head.add(band);
                [-0.215, 0.215].forEach(hx => {
                    const cup = new THREE.Mesh(new THREE.CylinderGeometry(0.06, 0.06, 0.04, 14), padMat);
                    cup.rotation.z = Math.PI / 2;
                    cup.position.set(hx, 0.01, 0);
                    head.add(cup);
                });
            }

            const glassesGroup = new THREE.Group();
            glassesGroup.name = 'glassesGroup';
            const gMat = new THREE.MeshStandardMaterial({ color: 0x1e293b });
            const lensMat = new THREE.MeshStandardMaterial({ color: 0x020617, transparent: true, opacity: 0.72 });
            [-0.068, 0.068].forEach(gx => {
                const rim = new THREE.Mesh(new THREE.TorusGeometry(0.042, 0.007, 8, 16), gMat);
                rim.position.set(gx, 0.025, 0.202);
                const lens = new THREE.Mesh(new THREE.CircleGeometry(0.038, 14), lensMat);
                lens.position.set(gx, 0.025, 0.203);
                glassesGroup.add(rim, lens);
            });
            const bridge = new THREE.Mesh(new THREE.BoxGeometry(0.05, 0.008, 0.008), gMat);
            bridge.position.set(0, 0.028, 0.203);
            glassesGroup.add(bridge);
            glassesGroup.visible = hasAcc('glasses');
            head.add(glassesGroup);

            const armRadius = 0.046 * Math.sqrt(wScale);
            const armLen = 0.48 * hScale;

            const leftArm = new THREE.Group();
            leftArm.name = 'leftArm';
            leftArm.position.set(-(shoulderW + armRadius * 0.7), 1.03 * hScale, 0);
            const lSleeve = new THREE.Mesh(new THREE.CylinderGeometry(armRadius * 1.15, armRadius * 1.1, armLen * 0.35, 10), topMat);
            lSleeve.position.y = -armLen * 0.17;
            const lForearm = new THREE.Mesh(new THREE.CylinderGeometry(armRadius, armRadius * 0.85, armLen * 0.65, 10), skinMat);
            lForearm.position.y = -armLen * 0.65;
            leftArm.add(lSleeve, lForearm);

            if (hasAcc('watch')) {
                const strap = new THREE.Mesh(new THREE.CylinderGeometry(armRadius * 0.96, armRadius * 0.96, 0.035, 12), new THREE.MeshStandardMaterial({ color: 0x111827 }));
                strap.position.y = -armLen * 0.88;
                const dial = new THREE.Mesh(new THREE.CylinderGeometry(0.026, 0.026, 0.015, 12), goldMat);
                dial.rotation.z = Math.PI / 2;
                dial.position.set(-armRadius * 0.92, -armLen * 0.88, 0);
                leftArm.add(strap, dial);
            }
            bodyPivot.add(leftArm);

            const rightArm = new THREE.Group();
            rightArm.name = 'rightArm';
            rightArm.position.set((shoulderW + armRadius * 0.7), 1.03 * hScale, 0);
            const rSleeve = lSleeve.clone();
            const rForearm = lForearm.clone();
            rightArm.add(rSleeve, rForearm);

            const cigGroup = new THREE.Group();
            cigGroup.name = 'propCigarette';
            const cigStick = new THREE.Mesh(new THREE.CylinderGeometry(0.008, 0.008, 0.09, 6), new THREE.MeshBasicMaterial({ color: 0xffffff }));
            cigStick.rotation.x = Math.PI / 2; cigStick.position.z = 0.05;
            const cigEmber = new THREE.Mesh(new THREE.SphereGeometry(0.01, 6, 6), new THREE.MeshBasicMaterial({ color: 0xf97316 }));
            cigEmber.position.z = 0.095;
            cigGroup.add(cigStick, cigEmber);
            cigGroup.position.set(0, -armLen * 0.95, 0);
            cigGroup.visible = false;
            rightArm.add(cigGroup);

            const gunGroup = new THREE.Group();
            gunGroup.name = 'propGun';
            const barrel = new THREE.Mesh(new THREE.BoxGeometry(0.035, 0.045, 0.18), new THREE.MeshStandardMaterial({ color: 0x111827, metalness: 0.8 }));
            barrel.position.set(0, -armLen * 0.95, 0.08);
            const grip = new THREE.Mesh(new THREE.BoxGeometry(0.032, 0.1, 0.045), new THREE.MeshStandardMaterial({ color: 0x1f2937 }));
            grip.position.set(0, -armLen * 0.99, 0.01);
            gunGroup.add(barrel, grip);
            gunGroup.visible = false;
            rightArm.add(gunGroup);

            const handoverMesh = new THREE.Mesh(new THREE.BoxGeometry(0.18, 0.02, 0.24), new THREE.MeshStandardMaterial({ color: 0xf8fafc }));
            handoverMesh.name = 'propHandover';
            handoverMesh.position.set(0, -armLen * 0.96, 0.12);
            handoverMesh.visible = false;
            rightArm.add(handoverMesh);

            bodyPivot.add(rightArm);

            const legRadius = 0.065 * Math.sqrt(wScale);
            const legLen = 0.48 * hScale;
            const isShorts = d.bottomType === 'shorts' || d.bottomType === 'skirt';

            ['leftLeg', 'rightLeg'].forEach((legName, idx) => {
                const side = idx === 0 ? -1 : 1;
                const legG = new THREE.Group();
                legG.name = legName;
                legG.position.set(side * hipW * 0.52, 0.48 * hScale, 0);

                if (isShorts) {
                    const upper = new THREE.Mesh(new THREE.CylinderGeometry(legRadius, legRadius * 0.92, legLen * 0.48, 12), botMat);
                    upper.position.y = -legLen * 0.24;
                    const lower = new THREE.Mesh(new THREE.CylinderGeometry(legRadius * 0.9, legRadius * 0.78, legLen * 0.52, 12), skinMat);
                    lower.position.y = -legLen * 0.74;
                    legG.add(upper, lower);
                } else {
                    const fullLeg = new THREE.Mesh(new THREE.CylinderGeometry(legRadius, legRadius * 0.82, legLen, 12), botMat);
                    fullLeg.position.y = -legLen * 0.5;
                    legG.add(fullLeg);
                }

                const shoeH = d.shoesType === 'boots' ? 0.16 : 0.07;
                const shoe = new THREE.Mesh(new THREE.BoxGeometry(legRadius * 2.15, shoeH, 0.22), shoeMat);
                shoe.position.set(0, -legLen + shoeH / 2, 0.04);
                legG.add(shoe);
                bodyPivot.add(legG);
            });

            g.traverse(m => { if (m.isMesh) { m.castShadow = true; m.receiveShadow = true; } });
            return g;
        }

export default {
    id: 'human',
    category: 'character',
    name: 'Человек (Аватар)',
    radius: 0.42,
    build: buildCharacter3D
};
